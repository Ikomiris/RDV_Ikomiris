<?php
namespace IBS\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class CRM {

    /**
     * Envoie les informations du client au CRM après une réservation
     *
     * @param int $store_id ID du magasin
     * @param array $customer_data Données du client (email, first_name, last_name, phone)
     * @return bool True si envoyé avec succès, False sinon
     */
    public function send_customer_to_crm($store_id, $customer_data) {
        global $wpdb;

        // Récupérer les paramètres CRM du magasin
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT crm_api_url, crm_tenant_id
            FROM {$wpdb->prefix}ibs_stores
            WHERE id = %d
        ", $store_id));

        // Vérifier si le CRM est configuré pour ce magasin
        if (!$store || empty($store->crm_api_url) || empty($store->crm_tenant_id)) {
            error_log('IBS CRM: Magasin #' . $store_id . ' sans configuration CRM - pas d\'envoi');
            return false;
        }

        // Valider les données du client
        if (empty($customer_data['email']) || empty($customer_data['first_name']) ||
            empty($customer_data['last_name']) || empty($customer_data['phone'])) {
            error_log('IBS CRM: Données client incomplètes pour magasin #' . $store_id);
            return false;
        }

        // Préparer les données à envoyer
        $post_data = [
            'tenant' => $store->crm_tenant_id,
            'email' => $customer_data['email'],
            'first_name' => $customer_data['first_name'],
            'last_name' => $customer_data['last_name'],
            'phone' => $customer_data['phone']
        ];

        // Log pour debug
        error_log('IBS CRM: Envoi des données client vers ' . $store->crm_api_url);
        error_log('IBS CRM: Données - ' . json_encode($post_data));

        // Envoyer la requête POST au CRM
        $response = wp_remote_post($store->crm_api_url, [
            'body' => $post_data,
            'timeout' => 15,
            'sslverify' => true,
            'headers' => []
        ]);

        // Vérifier les erreurs
        if (is_wp_error($response)) {
            error_log('IBS CRM: Erreur lors de l\'envoi - ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('IBS CRM: Réponse code ' . $response_code . ' - ' . $response_body);

        // Considérer comme succès les codes 2xx
        if ($response_code >= 200 && $response_code < 300) {
            error_log('IBS CRM: Client envoyé avec succès au CRM');
            return true;
        } else {
            error_log('IBS CRM: Échec de l\'envoi - Code ' . $response_code);
            return false;
        }
    }
}
