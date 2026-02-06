<?php
namespace IBS\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe d'intégration Google Calendar
 * 
 * Permet de récupérer les événements d'un calendrier Google pour éviter les doubles réservations
 * 
 * Documentation OAuth 2.0 : https://developers.google.com/identity/protocols/oauth2
 * API Google Calendar v3 : https://developers.google.com/calendar/api/v3/reference
 */
class GoogleCalendar {
    
    private $client_id;
    private $client_secret;
    private $refresh_token;
    private $enabled;
    
    /**
     * Constructeur - Charge les credentials depuis les settings
     */
    public function __construct() {
        $this->load_credentials();
    }
    
    /**
     * Charge les credentials depuis la table ibs_settings
     */
    private function load_credentials() {
        global $wpdb;
        $table = $wpdb->prefix . 'ibs_settings';
        
        // Récupérer tous les paramètres Google en une seule requête
        $settings = $wpdb->get_results(
            "SELECT setting_key, setting_value FROM $table 
             WHERE setting_key IN ('google_calendar_enabled', 'google_client_id', 'google_client_secret', 'google_refresh_token')",
            OBJECT_K
        );
        
        $this->enabled = isset($settings['google_calendar_enabled']) && $settings['google_calendar_enabled']->setting_value === '1';
        $this->client_id = isset($settings['google_client_id']) ? $settings['google_client_id']->setting_value : '';
        $this->client_secret = isset($settings['google_client_secret']) ? $settings['google_client_secret']->setting_value : '';
        $this->refresh_token = isset($settings['google_refresh_token']) ? $settings['google_refresh_token']->setting_value : '';
    }
    
    /**
     * Vérifie si Google Calendar est correctement configuré
     * 
     * @return bool True si tous les credentials sont présents et l'intégration est activée
     */
    public function is_configured() {
        return $this->enabled && 
               !empty($this->client_id) && 
               !empty($this->client_secret) && 
               !empty($this->refresh_token);
    }

    private function get_timezone_string() {
        $timezone = function_exists('wp_timezone_string') ? wp_timezone_string() : '';
        if (empty($timezone)) {
            $timezone = get_option('timezone_string');
        }
        if (empty($timezone)) {
            $timezone = 'UTC';
        }
        
        return $timezone;
    }
    
    private function get_api_timezone_string($timezone) {
        // Google n'accepte pas les offsets bruts (+02:00) pour timeZone
        if (preg_match('/^[+-]\d{2}:\d{2}$/', $timezone)) {
            return 'UTC';
        }
        
        return $timezone;
    }
    
    private function get_timezone_object($timezone) {
        try {
            return new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            return new \DateTimeZone('UTC');
        }
    }

    private function is_valid_timezone_string($timezone) {
        if (empty($timezone)) {
            return false;
        }
        
        try {
            new \DateTimeZone($timezone);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function infer_timezone_from_events($events, $fallback_timezone) {
        if (empty($events) || !is_array($events)) {
            return $fallback_timezone;
        }
        
        foreach ($events as $event) {
            if (isset($event['start']['timeZone']) && $this->is_valid_timezone_string($event['start']['timeZone'])) {
                return $event['start']['timeZone'];
            }
            
            if (isset($event['end']['timeZone']) && $this->is_valid_timezone_string($event['end']['timeZone'])) {
                return $event['end']['timeZone'];
            }
            
            if (isset($event['start']['dateTime']) && !empty($event['start']['dateTime'])) {
                try {
                    $dt = new \DateTime($event['start']['dateTime']);
                    $tz_name = $dt->getTimezone()->getName();
                    if ($this->is_valid_timezone_string($tz_name)) {
                        return $tz_name;
                    }
                } catch (\Exception $e) {
                    // Ignorer et continuer
                }
            }
        }
        
        return $fallback_timezone;
    }
    
    private function get_calendar_timezone($calendar_id, $access_token) {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendar_id);
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ],
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            $this->debug_log('calendar: wp_error=' . $response->get_error_message());
            return '';
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $this->debug_log('calendar: http=' . $status_code . ', body=' . $body);
            return '';
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($data['timeZone']) || empty($data['timeZone'])) {
            return '';
        }
        
        return $data['timeZone'];
    }

    public function get_calendar_timezone_for_id($calendar_id) {
        if (!$this->is_configured()) {
            $this->debug_log('calendar_timezone: non configure');
            return '';
        }

        if (empty($calendar_id)) {
            $this->debug_log('calendar_timezone: calendar_id vide');
            return '';
        }

        $access_token = $this->get_access_token();
        if (!$access_token) {
            $this->debug_log('calendar_timezone: access_token manquant');
            return '';
        }

        return $this->get_calendar_timezone($calendar_id, $access_token);
    }

    private function debug_log($message) {
        if (!defined('WP_CONTENT_DIR')) {
            return;
        }
        
        $path = WP_CONTENT_DIR . '/ibs-booking-debug.log';
        $line = '[' . gmdate('c') . '] ' . $message . PHP_EOL;
        error_log($line, 3, $path);
    }
    
    /**
     * Récupère les événements d'un calendrier pour une date donnée
     * 
     * @param string $calendar_id L'ID du calendrier Google (ex: exemple@group.calendar.google.com)
     * @param string $date Date au format Y-m-d (ex: 2025-01-15)
     * @return array Tableau d'objets avec booking_time et duration, ou tableau vide si échec
     */
    public function get_events_for_date($calendar_id, $date) {
        if (!$this->is_configured()) {
            error_log('IBS Google Calendar: Non configuré - intégration désactivée');
            $this->debug_log('get_events_for_date: non configure');
            return [];
        }
        
        if (empty($calendar_id)) {
            error_log('IBS Google Calendar: calendar_id vide');
            $this->debug_log('get_events_for_date: calendar_id vide');
            return [];
        }
        
        // Obtenir un access token valide
        $access_token = $this->get_access_token();
        if (!$access_token) {
            error_log('IBS Google Calendar: Impossible d\'obtenir un access token');
            $this->debug_log('get_events_for_date: access_token manquant');
            return [];
        }
        
        // Construire les paramètres de la requête avec le timezone WordPress/calendrier
        $timezone = $this->get_timezone_string();
        $calendar_timezone = $this->get_calendar_timezone($calendar_id, $access_token);
        $request_timezone = !empty($calendar_timezone) ? $calendar_timezone : $timezone;
        $api_timezone = $this->get_api_timezone_string($request_timezone);
        $time_min = $date . 'T00:00:00';
        $time_max = $date . 'T23:59:59';
        
        // Convertir en objets DateTime avec le timezone
        $timezone_obj = $this->get_timezone_object($request_timezone);
        $dt_min = new \DateTime($time_min, $timezone_obj);
        $dt_max = new \DateTime($time_max, $timezone_obj);
        
        // Utiliser UTC en RFC3339 avec suffixe Z pour eviter les offsets dans l'URL
        $dt_min_utc = clone $dt_min;
        $dt_max_utc = clone $dt_max;
        $dt_min_utc->setTimezone(new \DateTimeZone('UTC'));
        $dt_max_utc->setTimezone(new \DateTimeZone('UTC'));
        $time_min_formatted = $dt_min_utc->format('Y-m-d\TH:i:s\Z');
        $time_max_formatted = $dt_max_utc->format('Y-m-d\TH:i:s\Z');
        
        $url = add_query_arg([
            'timeMin' => $time_min_formatted,
            'timeMax' => $time_max_formatted,
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => 250,
        ], 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($calendar_id) . '/events');
        
        error_log('IBS Google Calendar: Récupération événements - Calendar: ' . $calendar_id . ', Date: ' . $date . ' (' . $timezone . ')');
        $this->debug_log('events: calendar_id=' . $calendar_id . ', date=' . $date . ', tz=' . $timezone . ', cal_tz=' . $calendar_timezone . ', req_tz=' . $request_timezone . ', api_tz=' . $api_timezone . ', timeMin=' . $time_min_formatted . ', timeMax=' . $time_max_formatted);
        
        // Effectuer la requête API
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ],
            'timeout' => 15,
        ]);
        
        // Gérer les erreurs
        if (is_wp_error($response)) {
            error_log('IBS Google Calendar: Erreur WP - ' . $response->get_error_message());
            $this->debug_log('events: wp_error=' . $response->get_error_message());
            return [];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('IBS Google Calendar: Erreur API (HTTP ' . $status_code . ') - ' . $body);
            $this->debug_log('events: http=' . $status_code . ', body=' . $body);
            return [];
        }
        
        // Parser la réponse JSON
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['items']) || !is_array($data['items'])) {
            $data['items'] = [];
        }
        
        // Ajuster le timezone de conversion si le calendrier ne fournit pas le sien
        $event_timezone = $request_timezone;
        if (empty($calendar_timezone)) {
            $event_timezone = $this->infer_timezone_from_events($data['items'], $request_timezone);
        }
        $timezone_obj = $this->get_timezone_object($event_timezone);
        
        // Convertir les événements Google au format compatible avec le plugin
        $events = $this->convert_events_to_bookings($data['items'], $date, $timezone_obj);
        
        // Toujours récupérer les créneaux occupés via freeBusy pour couvrir les cas silencieux
        $busy = $this->get_busy_for_date($calendar_id, $dt_min_utc, $dt_max_utc, $api_timezone);
        $busy_events = $this->convert_busy_to_bookings($busy, $date, $timezone_obj);
        $filtered_busy_events = array_values(array_filter($busy_events, function($booking) {
            $duration = isset($booking->duration) ? intval($booking->duration) : 0;
            return $duration < 1440;
        }));
        if (count($filtered_busy_events) !== count($busy_events)) {
            $this->debug_log('events: busy filtrés (' . count($busy_events) . ' -> ' . count($filtered_busy_events) . ')');
        }
        $busy_events = $filtered_busy_events;
        
        $this->debug_log('events: items=' . count($data['items']) . ', bookings=' . count($events) . ', busy=' . count($busy_events) . ', event_tz=' . $event_timezone);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('IBS Google Calendar: Events items=' . count($data['items']) . ', bookings=' . count($events) . ', busy=' . count($busy_events));
        }
        
        return array_merge($events, $busy_events);
    }
    
    /**
     * Convertit les événements Google au format des réservations WordPress
     * 
     * @param array $events Événements Google Calendar
     * @param string $date Date de référence (Y-m-d)
     * @return array Tableau d'objets compatibles avec la méthode generate_slots()
     */
    private function convert_events_to_bookings($events, $date, \DateTimeZone $timezone_obj) {
        $bookings = [];
        
        $day_start = new \DateTime($date . ' 00:00:00', $timezone_obj);
        $day_end = (clone $day_start)->modify('+1 day');
        
        error_log('IBS Google Calendar: Conversion de ' . count($events) . ' événement(s) pour la date ' . $date);
        
        foreach ($events as $event) {
            // Événements toute la journée (ou multi-jours)
            if (isset($event['start']['date'])) {
                $event_title = isset($event['summary']) ? $event['summary'] : 'Sans titre';

                // Vérifier si cet événement doit bloquer les réservations
                // Option 1: Vérifier si le titre contient des mots-clés spécifiques
                $blocking_keywords = apply_filters('ibs_google_calendar_blocking_keywords', [
                    'FERMÉ', 'FERME', 'CLOSED', 'CONGÉS', 'CONGES', 'VACANCES', 'FERMETURE'
                ]);

                $should_block = false;
                foreach ($blocking_keywords as $keyword) {
                    if (stripos($event_title, $keyword) !== false) {
                        $should_block = true;
                        break;
                    }
                }

                // Option 2: Par défaut, ne PAS bloquer les événements toute la journée
                // (permet aux jours fériés, anniversaires, etc. de ne pas bloquer les réservations)
                $block_all_day_events = apply_filters('ibs_google_calendar_block_all_day_events', false);

                if (!$should_block && !$block_all_day_events) {
                    error_log('IBS Google Calendar: Événement toute la journée IGNORÉ (ne bloque pas) - ' . $event_title);
                    continue;
                }

                $all_day_start = $event['start']['date'];
                $all_day_end = isset($event['end']['date']) ? $event['end']['date'] : $all_day_start;

                if ($date >= $all_day_start && $date < $all_day_end) {
                    $booking = (object)[
                        'booking_time' => '00:00:00',
                        'duration' => 1440,
                    ];

                    $bookings[] = $booking;
                    error_log('IBS Google Calendar: Événement toute la journée BLOQUANT - ' . $event_title);
                } else {
                    error_log('IBS Google Calendar: Événement toute la journée ignoré (hors date) - ' . $event_title);
                }

                continue;
            }
            
            // Récupérer les timestamps de début et fin
            $start = isset($event['start']['dateTime']) ? $event['start']['dateTime'] : null;
            $end = isset($event['end']['dateTime']) ? $event['end']['dateTime'] : null;
            
            if (!$start || !$end) {
                error_log('IBS Google Calendar: Événement ignoré (pas de dateTime) - ' . ($event['summary'] ?? 'Sans titre'));
                continue;
            }
            
            try {
                // Utiliser DateTime pour une meilleure gestion du timezone
                $dt_start = new \DateTime($start);
                $dt_end = new \DateTime($end);
                
                // Convertir au timezone WordPress
                $dt_start->setTimezone($timezone_obj);
                $dt_end->setTimezone($timezone_obj);
                
                // Vérifier le chevauchement avec la date demandée
                if ($dt_start >= $day_end || $dt_end <= $day_start) {
                    error_log('IBS Google Calendar: Événement ignoré (hors plage) - ' . ($event['summary'] ?? 'Sans titre'));
                    continue;
                }
                
                $effective_start = $dt_start > $day_start ? $dt_start : $day_start;
                $effective_end = $dt_end < $day_end ? $dt_end : $day_end;
                
                // Calculer la durée en minutes
                $duration = round(($effective_end->getTimestamp() - $effective_start->getTimestamp()) / 60);
                if ($duration <= 0) {
                    error_log('IBS Google Calendar: Événement ignoré (durée invalide) - ' . ($event['summary'] ?? 'Sans titre'));
                    continue;
                }
                
                // Créer un objet compatible avec is_slot_available()
                $booking = (object)[
                    'booking_time' => $effective_start->format('H:i:s'),
                    'duration' => $duration,
                ];
                
                $bookings[] = $booking;
                
                error_log('IBS Google Calendar: Événement ajouté - ' . ($event['summary'] ?? 'Sans titre') . ' à ' . $booking->booking_time . ' (' . $duration . ' min)');
                
            } catch (\Exception $e) {
                error_log('IBS Google Calendar: Erreur conversion événement - ' . $e->getMessage());
                continue;
            }
        }
        
        error_log('IBS Google Calendar: ' . count($bookings) . ' événement(s) converti(s) avec succès');
        
        return $bookings;
    }

    private function get_busy_for_date($calendar_id, \DateTime $dt_min, \DateTime $dt_max, $timezone) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            $this->debug_log('freeBusy: access_token manquant');
            return [];
        }
        
        $body = [
            'timeMin' => $dt_min->format('c'),
            'timeMax' => $dt_max->format('c'),
            'timeZone' => $timezone,
            'items' => [
                ['id' => $calendar_id],
            ],
        ];
        
        $response = wp_remote_post('https://www.googleapis.com/calendar/v3/freeBusy', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            error_log('IBS Google Calendar: Erreur freeBusy - ' . $response->get_error_message());
            $this->debug_log('freeBusy: wp_error=' . $response->get_error_message());
            return [];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $resp_body = wp_remote_retrieve_body($response);
            error_log('IBS Google Calendar: Erreur freeBusy (HTTP ' . $status_code . ') - ' . $resp_body);
            $this->debug_log('freeBusy: http=' . $status_code . ', body=' . $resp_body);
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($data['calendars'][$calendar_id]['busy']) || !is_array($data['calendars'][$calendar_id]['busy'])) {
            $this->debug_log('freeBusy: busy manquant pour calendar_id=' . $calendar_id);
            return [];
        }
        
        return $data['calendars'][$calendar_id]['busy'];
    }
    
    private function convert_busy_to_bookings($busy, $date, \DateTimeZone $timezone_obj) {
        $bookings = [];
        $day_start = new \DateTime($date . ' 00:00:00', $timezone_obj);
        $day_end = (clone $day_start)->modify('+1 day');
        
        foreach ($busy as $interval) {
            if (empty($interval['start']) || empty($interval['end'])) {
                continue;
            }
            
            try {
                $dt_start = new \DateTime($interval['start']);
                $dt_end = new \DateTime($interval['end']);
                $dt_start->setTimezone($timezone_obj);
                $dt_end->setTimezone($timezone_obj);
                
                if ($dt_start >= $day_end || $dt_end <= $day_start) {
                    continue;
                }
                
                $effective_start = $dt_start > $day_start ? $dt_start : $day_start;
                $effective_end = $dt_end < $day_end ? $dt_end : $day_end;
                
                $duration = round(($effective_end->getTimestamp() - $effective_start->getTimestamp()) / 60);
                if ($duration <= 0) {
                    continue;
                }
                
                $bookings[] = (object)[
                    'booking_time' => $effective_start->format('H:i:s'),
                    'duration' => $duration,
                ];
            } catch (\Exception $e) {
                error_log('IBS Google Calendar: Erreur conversion freeBusy - ' . $e->getMessage());
                continue;
            }
        }
        
        return $bookings;
    }
    
    /**
     * Obtient un access token valide via le refresh token
     * Utilise un transient WordPress pour le cache (55 minutes, les tokens expirent après 60 minutes)
     * 
     * @return string|false Access token ou false si échec
     */
    public function get_access_token() {
        // Vérifier le cache
        $cached_token = get_transient('ibs_google_access_token');
        if ($cached_token !== false) {
            return $cached_token;
        }
        
        // Obtenir un nouveau token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token',
            ],
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            error_log('IBS Google OAuth: Erreur - ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('IBS Google OAuth: Échec (HTTP ' . $status_code . ') - ' . $body);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            error_log('IBS Google OAuth: access_token manquant dans la réponse');
            return false;
        }
        
        $access_token = $data['access_token'];
        
        // Mettre en cache pour 55 minutes (les tokens expirent après 3600 secondes = 60 minutes)
        set_transient('ibs_google_access_token', $access_token, 55 * MINUTE_IN_SECONDS);
        
        return $access_token;
    }
    
    /**
     * Crée un événement dans Google Calendar (bonus pour synchronisation bidirectionnelle)
     * 
     * @param string $calendar_id L'ID du calendrier Google
     * @param array $event_data Données de l'événement
     *   - string 'summary' : Titre de l'événement
     *   - string 'description' : Description
     *   - string 'start' : Date/heure de début (ISO 8601)
     *   - string 'end' : Date/heure de fin (ISO 8601)
     * @return string|false Event ID ou false si échec
     */
    public function create_event($calendar_id, $event_data) {
        if (!$this->is_configured()) {
            error_log('IBS Google Calendar: Non configuré - impossible de créer un événement');
            return false;
        }
        
        $access_token = $this->get_access_token();
        if (!$access_token) {
            error_log('IBS Google Calendar: Impossible d\'obtenir un access token pour créer un événement');
            return false;
        }
        
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) . '/events';
        
        // Construire le body de l'événement
        // Les dates sont envoyées en UTC avec le format RFC3339 (avec Z)
        // Google Calendar comprendra automatiquement qu'il s'agit d'UTC
        $event = [
            'summary' => isset($event_data['summary']) ? $event_data['summary'] : 'Réservation Ikomiris',
            'description' => isset($event_data['description']) ? $event_data['description'] : '',
            'start' => [
                'dateTime' => $event_data['start'],
            ],
            'end' => [
                'dateTime' => $event_data['end'],
            ],
        ];

        // Log pour déboguer les problèmes de timezone
        error_log('IBS Google Calendar: Création événement - Start: ' . $event_data['start'] . ', End: ' . $event_data['end']);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($event),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            error_log('IBS Google Calendar: Erreur lors de la création - ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            $body = wp_remote_retrieve_body($response);
            error_log('IBS Google Calendar: Échec création (HTTP ' . $status_code . ') - ' . $body);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['id'])) {
            error_log('IBS Google Calendar: Event ID manquant dans la réponse');
            return false;
        }
        
        return $data['id'];
    }

    /**
     * Supprime un événement d'un calendrier
     *
     * @param string $calendar_id ID du calendrier
     * @param string $event_id ID de l'événement à supprimer
     * @return bool Succès ou échec
     */
    public function delete_event($calendar_id, $event_id) {
        if (!$this->is_configured()) {
            error_log('IBS Google Calendar: Non configuré - impossible de supprimer un événement');
            return false;
        }

        $access_token = $this->get_access_token();
        if (!$access_token) {
            error_log('IBS Google Calendar: Impossible d\'obtenir un access token pour supprimer un événement');
            return false;
        }

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) . '/events/' . urlencode($event_id);

        $response = wp_remote_request($url, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('IBS Google Calendar: Erreur lors de la suppression - ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        // 204 = succès (no content), 410 = événement déjà supprimé (considéré comme succès)
        if ($status_code === 204 || $status_code === 410) {
            error_log('IBS Google Calendar: Événement supprimé avec succès (event_id: ' . $event_id . ')');
            return true;
        }

        $body = wp_remote_retrieve_body($response);
        error_log('IBS Google Calendar: Échec suppression (HTTP ' . $status_code . ') - ' . $body);
        return false;
    }
}

