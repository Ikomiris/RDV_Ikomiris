<?php
namespace IBS\Security;

if (!defined('ABSPATH')) {
    exit;
}

class RateLimiter {

    /**
     * Vérifie si une action est autorisée selon les limites de taux
     *
     * @param string $action Type d'action (ex: 'create_booking')
     * @param string $identifier Identifiant unique (IP, user_id, email, etc.)
     * @param int $max_attempts Nombre maximum de tentatives
     * @param int $time_window Fenêtre de temps en secondes
     * @return array ['allowed' => bool, 'retry_after' => int]
     */
    public function check_rate_limit($action, $identifier, $max_attempts = 5, $time_window = 3600) {
        $transient_key = $this->get_transient_key($action, $identifier);

        // Récupérer les tentatives précédentes
        $attempts = get_transient($transient_key);

        if ($attempts === false) {
            // Première tentative dans cette fenêtre de temps
            $attempts = [
                'count' => 1,
                'first_attempt' => time(),
            ];
            set_transient($transient_key, $attempts, $time_window);

            return [
                'allowed' => true,
                'remaining' => $max_attempts - 1,
                'reset_at' => time() + $time_window,
            ];
        }

        // Vérifier si la limite est atteinte
        if ($attempts['count'] >= $max_attempts) {
            $time_elapsed = time() - $attempts['first_attempt'];
            $retry_after = $time_window - $time_elapsed;

            error_log(sprintf(
                'IBS Rate Limit: Action "%s" bloquée pour %s (tentatives: %d/%d)',
                $action,
                $identifier,
                $attempts['count'],
                $max_attempts
            ));

            return [
                'allowed' => false,
                'remaining' => 0,
                'retry_after' => max(0, $retry_after),
                'reset_at' => $attempts['first_attempt'] + $time_window,
            ];
        }

        // Incrémenter le compteur
        $attempts['count']++;
        set_transient($transient_key, $attempts, $time_window);

        return [
            'allowed' => true,
            'remaining' => $max_attempts - $attempts['count'],
            'reset_at' => $attempts['first_attempt'] + $time_window,
        ];
    }

    /**
     * Enregistre une tentative réussie (optionnel, pour analytics)
     *
     * @param string $action Type d'action
     * @param string $identifier Identifiant unique
     * @return void
     */
    public function record_success($action, $identifier) {
        $log_key = 'ibs_rate_limit_success_' . md5($action . $identifier);
        $success_count = get_transient($log_key);

        if ($success_count === false) {
            set_transient($log_key, 1, DAY_IN_SECONDS);
        } else {
            set_transient($log_key, $success_count + 1, DAY_IN_SECONDS);
        }
    }

    /**
     * Réinitialise les tentatives pour un identifiant
     *
     * @param string $action Type d'action
     * @param string $identifier Identifiant unique
     * @return void
     */
    public function reset_attempts($action, $identifier) {
        $transient_key = $this->get_transient_key($action, $identifier);
        delete_transient($transient_key);
    }

    /**
     * Génère la clé de transient pour le rate limiting
     *
     * @param string $action Type d'action
     * @param string $identifier Identifiant unique
     * @return string Clé de transient
     */
    private function get_transient_key($action, $identifier) {
        return 'ibs_rate_limit_' . md5($action . '_' . $identifier);
    }

    /**
     * Obtient l'adresse IP du visiteur
     *
     * @return string Adresse IP
     */
    public function get_client_ip() {
        // Vérifier les headers de proxy
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',    // Proxies standard
            'HTTP_X_REAL_IP',          // Nginx
            'REMOTE_ADDR',             // Connexion directe
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Si plusieurs IPs (proxy chain), prendre la première
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Valider l'IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Génère un identifiant unique combinant IP et user agent (empreinte basique)
     *
     * @return string Hash de l'empreinte
     */
    public function get_client_fingerprint() {
        $ip = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        return md5($ip . $user_agent);
    }

    /**
     * Vérifie le rate limit spécifiquement pour les réservations
     *
     * @return array Résultat du check
     */
    public function check_booking_rate_limit() {
        $fingerprint = $this->get_client_fingerprint();

        // Limites :
        // - 5 réservations par heure par empreinte
        // - 1 réservation par 10 minutes par empreinte
        $hourly_check = $this->check_rate_limit('create_booking_hourly', $fingerprint, 5, 3600);

        if (!$hourly_check['allowed']) {
            return $hourly_check;
        }

        $quick_check = $this->check_rate_limit('create_booking_quick', $fingerprint, 1, 600);

        return $quick_check;
    }

    /**
     * Bloque temporairement un identifiant (après détection d'abus)
     *
     * @param string $identifier Identifiant à bloquer
     * @param int $duration Durée du blocage en secondes (défaut: 1 heure)
     * @return void
     */
    public function block_identifier($identifier, $duration = 3600) {
        $block_key = 'ibs_blocked_' . md5($identifier);
        set_transient($block_key, true, $duration);

        error_log(sprintf(
            'IBS Security: Identifiant %s bloqué pour %d secondes',
            substr($identifier, 0, 8) . '...',
            $duration
        ));
    }

    /**
     * Vérifie si un identifiant est bloqué
     *
     * @param string $identifier Identifiant à vérifier
     * @return bool True si bloqué
     */
    public function is_blocked($identifier) {
        $block_key = 'ibs_blocked_' . md5($identifier);
        return get_transient($block_key) !== false;
    }

    /**
     * Formate un message d'erreur de rate limiting pour l'utilisateur
     *
     * @param array $result Résultat du check_rate_limit
     * @return string Message formaté
     */
    public function get_rate_limit_message($result) {
        if ($result['allowed']) {
            return '';
        }

        $retry_after = isset($result['retry_after']) ? $result['retry_after'] : 0;

        if ($retry_after > 3600) {
            $hours = ceil($retry_after / 3600);
            return sprintf(
                __('Trop de tentatives. Veuillez réessayer dans %d heure(s).', 'ikomiris-booking'),
                $hours
            );
        } elseif ($retry_after > 60) {
            $minutes = ceil($retry_after / 60);
            return sprintf(
                __('Trop de tentatives. Veuillez réessayer dans %d minute(s).', 'ikomiris-booking'),
                $minutes
            );
        } else {
            return sprintf(
                __('Trop de tentatives. Veuillez réessayer dans %d seconde(s).', 'ikomiris-booking'),
                $retry_after
            );
        }
    }
}
