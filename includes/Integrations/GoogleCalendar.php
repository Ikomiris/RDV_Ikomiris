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
            return [];
        }
        
        if (empty($calendar_id)) {
            error_log('IBS Google Calendar: calendar_id vide');
            return [];
        }
        
        // Obtenir un access token valide
        $access_token = $this->get_access_token();
        if (!$access_token) {
            error_log('IBS Google Calendar: Impossible d\'obtenir un access token');
            return [];
        }
        
        // Construire les paramètres de la requête avec le timezone WordPress
        $timezone = wp_timezone_string();
        $time_min = $date . 'T00:00:00';
        $time_max = $date . 'T23:59:59';
        
        // Convertir en objets DateTime avec le timezone
        $dt_min = new \DateTime($time_min, new \DateTimeZone($timezone));
        $dt_max = new \DateTime($time_max, new \DateTimeZone($timezone));
        
        // Formater en ISO 8601 avec timezone
        $time_min_formatted = $dt_min->format('c'); // Format: 2025-01-15T00:00:00+01:00
        $time_max_formatted = $dt_max->format('c');
        
        $url = add_query_arg([
            'timeMin' => $time_min_formatted,
            'timeMax' => $time_max_formatted,
            'singleEvents' => 'true',
            'orderBy' => 'startTime',
            'maxResults' => 250,
        ], 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) . '/events');
        
        error_log('IBS Google Calendar: Récupération événements - Calendar: ' . $calendar_id . ', Date: ' . $date . ' (' . $timezone . ')');
        
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
            return [];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('IBS Google Calendar: Erreur API (HTTP ' . $status_code . ') - ' . $body);
            return [];
        }
        
        // Parser la réponse JSON
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['items']) || !is_array($data['items'])) {
            return [];
        }
        
        // Convertir les événements Google au format compatible avec le plugin
        return $this->convert_events_to_bookings($data['items'], $date);
    }
    
    /**
     * Convertit les événements Google au format des réservations WordPress
     * 
     * @param array $events Événements Google Calendar
     * @param string $date Date de référence (Y-m-d)
     * @return array Tableau d'objets compatibles avec la méthode generate_slots()
     */
    private function convert_events_to_bookings($events, $date) {
        $bookings = [];
        $timezone = wp_timezone_string();
        
        error_log('IBS Google Calendar: Conversion de ' . count($events) . ' événement(s) pour la date ' . $date);
        
        foreach ($events as $event) {
            // Ignorer les événements toute la journée
            if (isset($event['start']['date'])) {
                error_log('IBS Google Calendar: Événement ignoré (toute la journée) - ' . ($event['summary'] ?? 'Sans titre'));
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
                $dt_start->setTimezone(new \DateTimeZone($timezone));
                $dt_end->setTimezone(new \DateTimeZone($timezone));
                
                // Vérifier que l'événement est bien sur la date demandée
                if ($dt_start->format('Y-m-d') !== $date) {
                    error_log('IBS Google Calendar: Événement ignoré (date différente: ' . $dt_start->format('Y-m-d') . ' != ' . $date . ') - ' . ($event['summary'] ?? 'Sans titre'));
                    continue;
                }
                
                // Calculer la durée en minutes
                $duration = round(($dt_end->getTimestamp() - $dt_start->getTimestamp()) / 60);
                
                // Créer un objet compatible avec is_slot_available()
                $booking = (object)[
                    'booking_time' => $dt_start->format('H:i:s'),
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

        $timezone = wp_timezone_string();
        if (!empty($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            $event['start']['timeZone'] = $timezone;
            $event['end']['timeZone'] = $timezone;
        }
        
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
}

