<?php
/**
 * Tests pour la classe RateLimiter
 */

use IBS\Security\RateLimiter;

class Test_RateLimiter extends WP_UnitTestCase {

    private $rate_limiter;

    public function setUp(): void {
        parent::setUp();
        $this->rate_limiter = new RateLimiter();
    }

    public function tearDown(): void {
        // Nettoyer les transients après chaque test
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ibs_rate_limit_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ibs_rate_limit_%'");
        parent::tearDown();
    }

    /**
     * Test : Première tentative doit être autorisée
     */
    public function test_first_attempt_allowed() {
        $result = $this->rate_limiter->check_rate_limit('test_action', 'test_user', 5, 3600);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(4, $result['remaining']);
        $this->assertArrayHasKey('reset_at', $result);
    }

    /**
     * Test : Limite atteinte après max_attempts
     */
    public function test_rate_limit_exceeded() {
        $identifier = 'test_user_' . time();

        // Faire 5 tentatives (max autorisé)
        for ($i = 1; $i <= 5; $i++) {
            $result = $this->rate_limiter->check_rate_limit('test_action', $identifier, 5, 3600);
            $this->assertTrue($result['allowed'], "Tentative $i devrait être autorisée");
        }

        // La 6ème tentative doit être refusée
        $result = $this->rate_limiter->check_rate_limit('test_action', $identifier, 5, 3600);
        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['remaining']);
        $this->assertArrayHasKey('retry_after', $result);
    }

    /**
     * Test : Réinitialisation des tentatives
     */
    public function test_reset_attempts() {
        $identifier = 'test_user_reset';

        // Faire 5 tentatives pour atteindre la limite
        for ($i = 0; $i < 5; $i++) {
            $this->rate_limiter->check_rate_limit('test_action', $identifier, 5, 3600);
        }

        // Vérifier que la limite est atteinte
        $result = $this->rate_limiter->check_rate_limit('test_action', $identifier, 5, 3600);
        $this->assertFalse($result['allowed']);

        // Réinitialiser
        $this->rate_limiter->reset_attempts('test_action', $identifier);

        // La prochaine tentative doit être autorisée
        $result = $this->rate_limiter->check_rate_limit('test_action', $identifier, 5, 3600);
        $this->assertTrue($result['allowed']);
    }

    /**
     * Test : Blocage d'un identifiant
     */
    public function test_block_identifier() {
        $identifier = 'blocked_user';

        $this->assertFalse($this->rate_limiter->is_blocked($identifier));

        $this->rate_limiter->block_identifier($identifier, 60);

        $this->assertTrue($this->rate_limiter->is_blocked($identifier));
    }

    /**
     * Test : Obtention de l'IP du client
     */
    public function test_get_client_ip() {
        // Simuler une IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $ip = $this->rate_limiter->get_client_ip();

        $this->assertEquals('192.168.1.100', $ip);
        $this->assertTrue(filter_var($ip, FILTER_VALIDATE_IP) !== false);
    }

    /**
     * Test : Génération d'empreinte client
     */
    public function test_get_client_fingerprint() {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';

        $fingerprint = $this->rate_limiter->get_client_fingerprint();

        $this->assertNotEmpty($fingerprint);
        $this->assertEquals(32, strlen($fingerprint)); // MD5 hash
    }

    /**
     * Test : Message de rate limit formaté
     */
    public function test_get_rate_limit_message() {
        $result_minutes = [
            'allowed' => false,
            'retry_after' => 120, // 2 minutes
        ];

        $message = $this->rate_limiter->get_rate_limit_message($result_minutes);
        $this->assertStringContainsString('minute', $message);

        $result_hours = [
            'allowed' => false,
            'retry_after' => 7200, // 2 heures
        ];

        $message = $this->rate_limiter->get_rate_limit_message($result_hours);
        $this->assertStringContainsString('heure', $message);
    }

    /**
     * Test : Check rate limit pour réservations
     */
    public function test_check_booking_rate_limit() {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.200';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';

        $result = $this->rate_limiter->check_booking_rate_limit();

        $this->assertTrue($result['allowed']);
        $this->assertArrayHasKey('remaining', $result);
    }

    /**
     * Test : Enregistrement de succès
     */
    public function test_record_success() {
        $identifier = 'success_user';

        // Ne doit pas lancer d'erreur
        $this->rate_limiter->record_success('test_action', $identifier);

        // Vérifier que le transient existe
        $log_key = 'ibs_rate_limit_success_' . md5('test_action' . $identifier);
        $this->assertNotFalse(get_transient($log_key));
    }
}
