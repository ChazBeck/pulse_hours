<?php

class ClientServiceTest extends DatabaseTestCase {
    private ClientService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new ClientService($this->pdo);
    }

    public function testCreateClientStoresAllFields(): void {
        $result = $this->service->createClient([
            'name'         => 'Initech',
            'client_color' => '#abcdef',
            'active'       => 1,
        ]);

        $this->assertTrue($result['success'], $result['message']);
        $row = $this->fetchOne('SELECT name, client_color, active FROM clients WHERE id = ?', [$result['client_id']]);
        $this->assertSame('Initech', $row['name']);
        $this->assertSame('#abcdef', $row['client_color']);
        $this->assertSame(1, (int) $row['active']);
    }

    public function testCreateClientRequiresName(): void {
        $result = $this->service->createClient(['name' => '   ']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('name is required', $result['message']);
    }

    public function testCreateClientRejectsMalformedColor(): void {
        $result = $this->service->createClient([
            'name'         => 'Initech',
            'client_color' => 'not-a-color',
        ]);
        $this->assertTrue($result['success']);
        $row = $this->fetchOne('SELECT client_color FROM clients WHERE id = ?', [$result['client_id']]);
        $this->assertSame('#3b82f6', $row['client_color'], 'malformed color should fall back to default');
    }

    public function testUpdateClientPersistsActiveFlagWhenUnchecked(): void {
        $created = $this->service->createClient(['name' => 'Globex Two', 'active' => 1]);

        // Form did not include 'active' → checkbox unchecked.
        $result = $this->service->updateClient($created['client_id'], [
            'name' => 'Globex Two Renamed',
        ]);

        $this->assertTrue($result['success'], $result['message']);
        $row = $this->fetchOne('SELECT name, active FROM clients WHERE id = ?', [$created['client_id']]);
        $this->assertSame('Globex Two Renamed', $row['name']);
        $this->assertSame(0, (int) $row['active']);
    }

    public function testDeleteClientRemovesRow(): void {
        $result = $this->service->deleteClient($this->fixtures['globex_id']);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->countWhere('clients', 'id = ?', [$this->fixtures['globex_id']]));
    }
}
