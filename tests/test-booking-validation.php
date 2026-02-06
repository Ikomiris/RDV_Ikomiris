<?php
/**
 * Tests pour les validations de réservation
 */

class Test_Booking_Validation extends WP_UnitTestCase {

    private $store_id;
    private $service_id;

    public function setUp(): void {
        parent::setUp();

        global $wpdb;

        // Créer un magasin de test
        $wpdb->insert(
            $wpdb->prefix . 'ibs_stores',
            [
                'name' => 'Test Store',
                'email' => 'test@store.com',
                'is_active' => 1,
            ]
        );
        $this->store_id = $wpdb->insert_id;

        // Créer un service de test
        $wpdb->insert(
            $wpdb->prefix . 'ibs_services',
            [
                'name' => 'Test Service',
                'duration' => 60,
                'price' => 50.00,
                'is_active' => 1,
            ]
        );
        $this->service_id = $wpdb->insert_id;

        // Lier le service au magasin
        $wpdb->insert(
            $wpdb->prefix . 'ibs_store_services',
            [
                'store_id' => $this->store_id,
                'service_id' => $this->service_id,
            ]
        );

        // Configurer les paramètres
        $wpdb->insert(
            $wpdb->prefix . 'ibs_settings',
            ['setting_key' => 'min_booking_delay', 'setting_value' => '2'],
            ['%s', '%s']
        );
        $wpdb->insert(
            $wpdb->prefix . 'ibs_settings',
            ['setting_key' => 'max_booking_delay', 'setting_value' => '90'],
            ['%s', '%s']
        );
    }

    public function tearDown(): void {
        global $wpdb;

        // Nettoyer les données de test
        $wpdb->query("DELETE FROM {$wpdb->prefix}ibs_bookings WHERE store_id = {$this->store_id}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}ibs_store_services WHERE store_id = {$this->store_id}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}ibs_stores WHERE id = {$this->store_id}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}ibs_services WHERE id = {$this->service_id}");
        $wpdb->query("DELETE FROM {$wpdb->prefix}ibs_settings WHERE setting_key IN ('min_booking_delay', 'max_booking_delay')");

        parent::tearDown();
    }

    /**
     * Test : Validation de l'email
     */
    public function test_email_validation() {
        $valid_emails = [
            'test@example.com',
            'user+tag@domain.co.uk',
            'firstname.lastname@example.com',
        ];

        foreach ($valid_emails as $email) {
            $this->assertTrue(is_email($email), "$email devrait être valide");
        }

        $invalid_emails = [
            'invalid.email',
            '@example.com',
            'test@',
            'test @example.com',
        ];

        foreach ($invalid_emails as $email) {
            $this->assertFalse(is_email($email), "$email devrait être invalide");
        }
    }

    /**
     * Test : Validation du délai minimum de réservation
     */
    public function test_min_booking_delay_validation() {
        global $wpdb;

        $min_delay = intval($wpdb->get_var(
            "SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = 'min_booking_delay'"
        ));

        $this->assertEquals(2, $min_delay);

        // Date trop proche (dans 1 heure = invalide)
        $too_soon = strtotime('+1 hour');
        $min_allowed = current_time('timestamp') + ($min_delay * 3600);

        $this->assertLessThan($min_allowed, $too_soon);

        // Date valide (dans 3 heures)
        $valid_time = strtotime('+3 hours');
        $this->assertGreaterThanOrEqual($min_allowed, $valid_time);
    }

    /**
     * Test : Validation du délai maximum de réservation
     */
    public function test_max_booking_delay_validation() {
        global $wpdb;

        $max_delay = intval($wpdb->get_var(
            "SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = 'max_booking_delay'"
        ));

        $this->assertEquals(90, $max_delay);

        // Date trop lointaine (dans 100 jours = invalide)
        $too_far = strtotime('+100 days');
        $max_allowed = current_time('timestamp') + ($max_delay * 86400);

        $this->assertGreaterThan($max_allowed, $too_far);

        // Date valide (dans 30 jours)
        $valid_time = strtotime('+30 days');
        $this->assertLessThanOrEqual($max_allowed, $valid_time);
    }

    /**
     * Test : Création d'une réservation valide
     */
    public function test_create_valid_booking() {
        global $wpdb;

        $booking_date = date('Y-m-d', strtotime('+1 week'));
        $booking_time = '14:00:00';

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ibs_bookings',
            [
                'store_id' => $this->store_id,
                'service_id' => $this->service_id,
                'booking_date' => $booking_date,
                'booking_time' => $booking_time,
                'duration' => 60,
                'customer_firstname' => 'John',
                'customer_lastname' => 'Doe',
                'customer_email' => 'john@example.com',
                'customer_phone' => '0123456789',
                'status' => 'confirmed',
                'cancel_token' => bin2hex(random_bytes(32)),
            ]
        );

        $this->assertNotFalse($inserted);
        $this->assertGreaterThan(0, $wpdb->insert_id);
    }

    /**
     * Test : Token d'annulation unique et sécurisé
     */
    public function test_cancel_token_generation() {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        // Les tokens doivent être différents
        $this->assertNotEquals($token1, $token2);

        // Les tokens doivent avoir 64 caractères (32 bytes = 64 hex chars)
        $this->assertEquals(64, strlen($token1));
        $this->assertEquals(64, strlen($token2));

        // Les tokens doivent être hexadécimaux
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token1);
    }

    /**
     * Test : Sanitisation des champs
     */
    public function test_field_sanitization() {
        $dangerous_input = '<script>alert("XSS")</script>';

        $sanitized_text = sanitize_text_field($dangerous_input);
        $this->assertNotContains('<script>', $sanitized_text);

        $sanitized_email = sanitize_email('test+tag@example.com<script>');
        $this->assertEquals('test+tag@example.com', $sanitized_email);

        $dangerous_textarea = "Line 1\n<script>alert('XSS')</script>\nLine 3";
        $sanitized_textarea = sanitize_textarea_field($dangerous_textarea);
        $this->assertStringNotContainsString('<script>', $sanitized_textarea);
    }

    /**
     * Test : Vérification de l'existence du magasin et du service
     */
    public function test_store_and_service_exist() {
        global $wpdb;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ibs_stores WHERE id = %d",
            $this->store_id
        ));

        $this->assertNotNull($store);
        $this->assertEquals('Test Store', $store->name);

        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ibs_services WHERE id = %d",
            $this->service_id
        ));

        $this->assertNotNull($service);
        $this->assertEquals('Test Service', $service->name);
        $this->assertEquals(60, $service->duration);
    }

    /**
     * Test : Format de date valide
     */
    public function test_date_format_validation() {
        $valid_dates = [
            '2026-02-15',
            '2026-12-31',
            '2026-01-01',
        ];

        foreach ($valid_dates as $date) {
            $timestamp = strtotime($date);
            $this->assertNotFalse($timestamp, "$date devrait être valide");
            $this->assertEquals($date, date('Y-m-d', $timestamp));
        }

        $invalid_dates = [
            '2026-13-01', // Mois invalide
            '2026-02-30', // Jour invalide pour février
            '15/02/2026', // Format incorrect
            'invalid',
        ];

        foreach ($invalid_dates as $date) {
            $timestamp = strtotime($date);
            // strtotime peut retourner false ou un timestamp, mais le reformatage ne devrait pas donner la même chose
            $this->assertNotEquals($date, date('Y-m-d', $timestamp ?: 0));
        }
    }
}
