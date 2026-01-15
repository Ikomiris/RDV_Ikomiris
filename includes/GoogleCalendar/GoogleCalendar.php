<?php
namespace IBS\GoogleCalendar;

if (!defined('ABSPATH')) {
    exit;
}

class GoogleCalendar {
    
    private $client_id;
    private $client_secret;
    private $refresh_token;
    private $access_token;
    private $token_expires_at;
    
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const CALENDAR_API_URL = 'https://www.googleapis.com/calendar/v3';
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    
    public function __construct() {
        try {
            global $wpdb;
            
            if (!isset($wpdb) || !$wpdb) {
                // Si $wpdb n'est pas disponible, initialiser avec des valeurs vides
                $this->client_id = '';
                $this->client_secret = '';
                $this->refresh_token = '';
                $this->access_token = '';
                $this->token_expires_at = 0;
                return;
            }
            
            $table = $wpdb->prefix . 'ibs_settings';
            
            // Vérifier que la table existe avant de faire la requête
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            
            if (!$table_exists) {
                // Si la table n'existe pas, initialiser avec des valeurs vides
                $this->client_id = '';
                $this->client_secret = '';
                $this->refresh_token = '';
                $this->access_token = '';
                $this->token_expires_at = 0;
                return;
            }
            
            $settings = $wpdb->get_results("SELECT setting_key, setting_value FROM $table WHERE setting_key IN ('google_client_id', 'google_client_secret', 'google_refresh_token', 'google_access_token', 'google_token_expires_at')", OBJECT_K);
            
            if ($settings === false) {
                // En cas d'erreur SQL, initialiser avec des valeurs vides
                $this->client_id = '';
                $this->client_secret = '';
                $this->refresh_token = '';
                $this->access_token = '';
                $this->token_expires_at = 0;
                return;
            }
            
            $this->client_id = isset($settings['google_client_id']) && is_object($settings['google_client_id']) ? $settings['google_client_id']->setting_value : '';
            $this->client_secret = isset($settings['google_client_secret']) && is_object($settings['google_client_secret']) ? $settings['google_client_secret']->setting_value : '';
            $this->refresh_token = isset($settings['google_refresh_token']) && is_object($settings['google_refresh_token']) ? $settings['google_refresh_token']->setting_value : '';
            $this->access_token = isset($settings['google_access_token']) && is_object($settings['google_access_token']) ? $settings['google_access_token']->setting_value : '';
            $this->token_expires_at = isset($settings['google_token_expires_at']) && is_object($settings['google_token_expires_at']) ? intval($settings['google_token_expires_at']->setting_value) : 0;
        } catch (\Exception $e) {
            // En cas d'erreur, initialiser avec des valeurs vides
            $this->client_id = '';
            $this->client_secret = '';
            $this->refresh_token = '';
            $this->access_token = '';
            $this->token_expires_at = 0;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("IBS GoogleCalendar Constructor Error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Vérifie si Google Calendar est activé et configuré
     */
    public function is_enabled() {
        try {
            global $wpdb;
            
            if (!isset($wpdb) || !$wpdb) {
                return false;
            }
            
            $table = $wpdb->prefix . 'ibs_settings';
            
            // Vérifier que la table existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            if (!$table_exists) {
                return false;
            }
            
            $enabled = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s", 'google_calendar_enabled'));
            
            return $enabled === '1' && !empty($this->client_id) && !empty($this->client_secret) && !empty($this->refresh_token);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("IBS GoogleCalendar is_enabled Error: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Génère l'URL d'autorisation OAuth2
     */
    public function get_authorization_url() {
        if (empty($this->client_id)) {
            return '';
        }
        
        $redirect_uri = admin_url('admin.php?page=ikomiris-booking-settings&action=google_calendar_callback');
        
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return self::AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Échange le code d'autorisation contre un token
     */
    public function exchange_code_for_token($code) {
        $redirect_uri = admin_url('admin.php?page=ikomiris-booking-settings&action=google_calendar_callback');
        
        $data = [
            'code' => $code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code'
        ];
        
        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => $data,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return false;
        }
        
        // Sauvegarder les tokens
        $this->save_tokens($body);
        
        return true;
    }
    
    /**
     * Sauvegarde les tokens dans la base de données
     */
    private function save_tokens($token_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ibs_settings';
        
        $this->access_token = $token_data['access_token'];
        $expires_in = isset($token_data['expires_in']) ? intval($token_data['expires_in']) : 3600;
        $this->token_expires_at = time() + $expires_in;
        
        if (isset($token_data['refresh_token'])) {
            $this->refresh_token = $token_data['refresh_token'];
        }
        
        // Sauvegarder access_token
        $wpdb->replace($table, [
            'setting_key' => 'google_access_token',
            'setting_value' => $this->access_token
        ], ['%s', '%s']);
        
        // Sauvegarder refresh_token
        if (!empty($this->refresh_token)) {
            $wpdb->replace($table, [
                'setting_key' => 'google_refresh_token',
                'setting_value' => $this->refresh_token
            ], ['%s', '%s']);
        }
        
        // Sauvegarder expiration
        $wpdb->replace($table, [
            'setting_key' => 'google_token_expires_at',
            'setting_value' => (string)$this->token_expires_at
        ], ['%s', '%s']);
    }
    
    /**
     * Rafraîchit le token d'accès si nécessaire
     */
    private function ensure_valid_token() {
        // Vérifier si le token est expiré (avec une marge de 5 minutes)
        if (time() >= ($this->token_expires_at - 300)) {
            $this->refresh_access_token();
        }
    }
    
    /**
     * Rafraîchit le token d'accès
     */
    private function refresh_access_token() {
        if (empty($this->refresh_token)) {
            return false;
        }
        
        $data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $this->refresh_token,
            'grant_type' => 'refresh_token'
        ];
        
        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => $data,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return false;
        }
        
        $this->save_tokens($body);
        
        return true;
    }
    
    /**
     * Effectue une requête API authentifiée
     */
    private function api_request($method, $endpoint, $data = null) {
        if (!$this->is_enabled()) {
            return false;
        }
        
        $this->ensure_valid_token();
        
        $url = self::CALENDAR_API_URL . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ];
        
        if ($data !== null) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code >= 200 && $status_code < 300) {
            return $body;
        }
        
        return false;
    }
    
    /**
     * Crée un événement dans Google Calendar
     */
    public function create_event($booking) {
        global $wpdb;
        
        // Récupérer les informations du magasin et du service
        $store = $wpdb->get_row($wpdb->prepare("SELECT name, address FROM {$wpdb->prefix}ibs_stores WHERE id = %d", $booking->store_id));
        $service = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}ibs_services WHERE id = %d", $booking->service_id));
        
        if (!$store || !$service) {
            return false;
        }
        
        // Construire la date/heure de début
        $start_datetime = $booking->booking_date . 'T' . $booking->booking_time;
        $start = new \DateTime($start_datetime);
        
        // Construire la date/heure de fin
        $end = clone $start;
        $end->modify('+' . $booking->duration . ' minutes');
        
        // Description de l'événement
        $description = sprintf(
            "Client: %s %s\nEmail: %s\nTéléphone: %s\nService: %s\nMagasin: %s",
            $booking->customer_firstname,
            $booking->customer_lastname,
            $booking->customer_email,
            $booking->customer_phone,
            $service->name,
            $store->name
        );
        
        if (!empty($booking->customer_message)) {
            $description .= "\n\nMessage: " . $booking->customer_message;
        }
        
        // Localisation
        $location = $store->name;
        if (!empty($store->address)) {
            $location .= ' - ' . $store->address;
        }
        
        $event_data = [
            'summary' => sprintf('%s - %s %s', $service->name, $booking->customer_firstname, $booking->customer_lastname),
            'description' => $description,
            'start' => [
                'dateTime' => $start->format('c'),
                'timeZone' => function_exists('wp_timezone_string') ? wp_timezone_string() : (get_option('timezone_string') ?: 'Europe/Paris')
            ],
            'end' => [
                'dateTime' => $end->format('c'),
                'timeZone' => function_exists('wp_timezone_string') ? wp_timezone_string() : (get_option('timezone_string') ?: 'Europe/Paris')
            ],
            'location' => $location,
            'attendees' => [
                [
                    'email' => $booking->customer_email,
                    'displayName' => $booking->customer_firstname . ' ' . $booking->customer_lastname
                ]
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 1440], // 24h avant
                    ['method' => 'popup', 'minutes' => 30]    // 30 min avant
                ]
            ]
        ];
        
        $result = $this->api_request('POST', '/calendars/primary/events', $event_data);
        
        if ($result && isset($result['id'])) {
            // Sauvegarder l'ID de l'événement dans la réservation
            $updated = $wpdb->update(
                $wpdb->prefix . 'ibs_bookings',
                ['google_event_id' => $result['id']],
                ['id' => $booking->id],
                ['%s'],
                ['%d']
            );
            
            if ($updated === false && $wpdb->last_error) {
                error_log("IBS Google Calendar: Failed to save event ID to database. Error: " . $wpdb->last_error);
            }
            
            return $result['id'];
        }
        
        // Logger l'erreur si la création a échoué
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("IBS Google Calendar: Failed to create event. Result: " . print_r($result, true));
        }
        
        return false;
    }
    
    /**
     * Met à jour un événement dans Google Calendar
     */
    public function update_event($booking) {
        if (empty($booking->google_event_id)) {
            // Si pas d'ID, créer l'événement
            return $this->create_event($booking);
        }
        
        global $wpdb;
        
        // Récupérer les informations du magasin et du service
        $store = $wpdb->get_row($wpdb->prepare("SELECT name, address FROM {$wpdb->prefix}ibs_stores WHERE id = %d", $booking->store_id));
        $service = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}ibs_services WHERE id = %d", $booking->service_id));
        
        if (!$store || !$service) {
            return false;
        }
        
        // Construire la date/heure de début
        $start_datetime = $booking->booking_date . 'T' . $booking->booking_time;
        $start = new \DateTime($start_datetime);
        
        // Construire la date/heure de fin
        $end = clone $start;
        $end->modify('+' . $booking->duration . ' minutes');
        
        // Description de l'événement
        $description = sprintf(
            "Client: %s %s\nEmail: %s\nTéléphone: %s\nService: %s\nMagasin: %s",
            $booking->customer_firstname,
            $booking->customer_lastname,
            $booking->customer_email,
            $booking->customer_phone,
            $service->name,
            $store->name
        );
        
        if (!empty($booking->customer_message)) {
            $description .= "\n\nMessage: " . $booking->customer_message;
        }
        
        // Localisation
        $location = $store->name;
        if (!empty($store->address)) {
            $location .= ' - ' . $store->address;
        }
        
        $event_data = [
            'summary' => sprintf('%s - %s %s', $service->name, $booking->customer_firstname, $booking->customer_lastname),
            'description' => $description,
            'start' => [
                'dateTime' => $start->format('c'),
                'timeZone' => function_exists('wp_timezone_string') ? wp_timezone_string() : (get_option('timezone_string') ?: 'Europe/Paris')
            ],
            'end' => [
                'dateTime' => $end->format('c'),
                'timeZone' => function_exists('wp_timezone_string') ? wp_timezone_string() : (get_option('timezone_string') ?: 'Europe/Paris')
            ],
            'location' => $location,
            'attendees' => [
                [
                    'email' => $booking->customer_email,
                    'displayName' => $booking->customer_firstname . ' ' . $booking->customer_lastname
                ]
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 1440],
                    ['method' => 'popup', 'minutes' => 30]
                ]
            ]
        ];
        
        $result = $this->api_request('PUT', '/calendars/primary/events/' . urlencode($booking->google_event_id), $event_data);
        
        return $result !== false;
    }
    
    /**
     * Supprime un événement dans Google Calendar
     */
    public function delete_event($google_event_id) {
        if (empty($google_event_id)) {
            return false;
        }
        
        $result = $this->api_request('DELETE', '/calendars/primary/events/' . urlencode($google_event_id));
        
        return $result !== false;
    }
    
    /**
     * Récupère les événements Google Calendar pour une date donnée
     */
    public function get_events_for_date($date) {
        if (!$this->is_enabled()) {
            return [];
        }
        
        $this->ensure_valid_token();
        
        // Construire les dates de début et fin pour la journée
        // Utiliser wp_timezone_string() si disponible, sinon utiliser get_option('timezone_string')
        if (function_exists('wp_timezone_string')) {
            $timezone = wp_timezone_string();
        } else {
            $timezone = get_option('timezone_string');
            if (empty($timezone)) {
                $timezone = 'Europe/Paris'; // Fallback par défaut
            }
        }
        
        try {
            $timezone_obj = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("IBS Google Calendar: Invalid timezone '$timezone', using UTC");
            }
            $timezone_obj = new \DateTimeZone('UTC');
        }
        
        $start_datetime = new \DateTime($date . ' 00:00:00', $timezone_obj);
        $end_datetime = new \DateTime($date . ' 23:59:59', $timezone_obj);
        
        // Formater pour l'API Google Calendar (format RFC3339)
        $time_min = $start_datetime->format('c');
        $time_max = $end_datetime->format('c');
        
        // Construire l'URL avec les paramètres
        $url = self::CALENDAR_API_URL . '/calendars/primary/events';
        $url .= '?timeMin=' . urlencode($time_min);
        $url .= '&timeMax=' . urlencode($time_max);
        $url .= '&singleEvents=true';
        $url .= '&orderBy=startTime';
        
        $args = [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ];
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("IBS Google Calendar: Error fetching events - " . $response->get_error_message());
            }
            return [];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($status_code < 200 || $status_code >= 300 || !isset($body['items'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("IBS Google Calendar: Failed to fetch events. Status: $status_code, Body: " . print_r($body, true));
            }
            return [];
        }
        
        $events = [];
        foreach ($body['items'] as $event) {
            // Ignorer les événements annulés
            if (isset($event['status']) && $event['status'] === 'cancelled') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("IBS Google Calendar: Skipping cancelled event");
                }
                continue;
            }
            
            // Récupérer l'heure de début (priorité à dateTime, sinon date pour événements toute la journée)
            $start_datetime = null;
            if (isset($event['start']['dateTime'])) {
                $start_datetime = $event['start']['dateTime'];
            } elseif (isset($event['start']['date'])) {
                // Événement toute la journée - on l'ignore car il bloque toute la journée
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("IBS Google Calendar: Skipping all-day event");
                }
                continue;
            }
            
            if ($start_datetime) {
                try {
                    $start = new \DateTime($start_datetime);
                    // Convertir dans le fuseau horaire du site
                    if (function_exists('wp_timezone_string')) {
                        $site_timezone = wp_timezone_string();
                    } else {
                        $site_timezone = get_option('timezone_string');
                        if (empty($site_timezone)) {
                            $site_timezone = 'Europe/Paris';
                        }
                    }
                    
                    try {
                        $site_tz_obj = new \DateTimeZone($site_timezone);
                    } catch (\Exception $e) {
                        $site_tz_obj = new \DateTimeZone('UTC');
                    }
                    
                    $start->setTimezone($site_tz_obj);
                    $start_time = $start->format('H:i:s');
                    
                    // Vérifier que l'événement est bien le jour demandé
                    $event_date = $start->format('Y-m-d');
                    if ($event_date !== $date) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("IBS Google Calendar: Skipping event - date mismatch. Event date: $event_date, Requested: $date");
                        }
                        continue;
                    }
                    
                    // Calculer la durée
                    $end_datetime = isset($event['end']['dateTime']) ? $event['end']['dateTime'] : null;
                    $duration = 60; // Par défaut 60 minutes si pas de fin
                    
                    if ($end_datetime) {
                        $end = new \DateTime($end_datetime);
                        $end->setTimezone($site_tz_obj);
                        $diff = $start->diff($end);
                        $duration = ($diff->h * 60) + $diff->i;
                        
                        // Si la durée est 0 ou négative, utiliser 60 minutes par défaut
                        if ($duration <= 0) {
                            $duration = 60;
                        }
                    }
                    
                    // Logger pour débogage
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("IBS Google Calendar: Event found - Date: $event_date, Time: $start_time, Duration: $duration minutes, Summary: " . (isset($event['summary']) ? $event['summary'] : 'N/A'));
                    }
                    
                    $events[] = (object)[
                        'booking_time' => $start_time,
                        'duration' => $duration
                    ];
                } catch (\Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("IBS Google Calendar: Error processing event - " . $e->getMessage());
                    }
                    continue;
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("IBS Google Calendar: Total events found for date $date: " . count($events));
        }
        
        return $events;
    }
    
    /**
     * Déconnecte Google Calendar (supprime les tokens)
     */
    public function disconnect() {
        try {
            global $wpdb;
            
            if (!isset($wpdb) || !$wpdb) {
                return;
            }
            
            $table = $wpdb->prefix . 'ibs_settings';
            
            // Vérifier que la table existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
            if (!$table_exists) {
                return;
            }
            
            $wpdb->delete($table, ['setting_key' => 'google_refresh_token'], ['%s']);
            $wpdb->delete($table, ['setting_key' => 'google_access_token'], ['%s']);
            $wpdb->delete($table, ['setting_key' => 'google_token_expires_at'], ['%s']);
            
            $this->refresh_token = '';
            $this->access_token = '';
            $this->token_expires_at = 0;
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("IBS GoogleCalendar disconnect Error: " . $e->getMessage());
            }
        }
    }
}

