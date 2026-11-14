<?php

namespace Tests\Feature;

use App\Models\FotocopyOrder;
use App\Models\FotocopyOrderItem;
use App\Models\FotocopyStore;
use App\Models\PhotocopyPlace;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\Strategies\BankTransferPaymentStrategy;
use App\Services\Payment\Strategies\CashPaymentStrategy;
use App\Services\Payment\Strategies\QrisPaymentStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotocopyPaymentStrategyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AdminUserSeeder::class);
        $this->seed(\Database\Seeders\FotocopySeeder::class);
    }

    public function test_payment_manager_resolves_correct_strategies(): void
    {
        $manager = app(PaymentManager::class);

        $this->assertInstanceOf(CashPaymentStrategy::class, $manager->getStrategy('cash'));
        $this->assertInstanceOf(CashPaymentStrategy::class, $manager->getStrategy('tunai'));
        $this->assertInstanceOf(CashPaymentStrategy::class, $manager->getStrategy('cod'));

        $this->assertInstanceOf(QrisPaymentStrategy::class, $manager->getStrategy('qris'));
        $this->assertInstanceOf(QrisPaymentStrategy::class, $manager->getStrategy('QRIS'));

        $this->assertInstanceOf(BankTransferPaymentStrategy::class, $manager->getStrategy('bank_transfer'));
        $this->assertInstanceOf(BankTransferPaymentStrategy::class, $manager->getStrategy('transfer'));
        $this->assertInstanceOf(BankTransferPaymentStrategy::class, $manager->getStrategy('bank'));
    }

    public function test_cash_payment_strategy_initializes_order(): void
    {
        $store = FotocopyStore::first() ?? FotocopyStore::create([
            'name' => 'Berkah Fotokopi USU',
            'address' => 'Jl. Dr. Mansyur No. 9',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $order = FotocopyOrder::create([
            'order_code' => 'ORD-TEST-CASH-1',
            'store_id' => $store->id,
            'customer_name' => 'Budi Santoso',
            'payment_method' => 'cash',
            'total_price' => 15000,
        ]);

        $manager = app(PaymentManager::class);
        $result = $manager->process($order, 'cash');

        $this->assertTrue($result->success);
        $this->assertEquals('cash', $order->fresh()->payment_method);
        $this->assertEquals('CASHIER', $order->fresh()->payment_channel);
        $this->assertEquals('pending', $order->fresh()->payment_status);
        $this->assertNotEmpty($order->fresh()->payment_payload);
        $this->assertNotEmpty($order->fresh()->payment_payload['instructions']);
    }

    public function test_qris_payment_strategy_generates_unique_dynamic_payload_and_svg(): void
    {
        $store = FotocopyStore::first() ?? FotocopyStore::create([
            'name' => 'Berkah Fotokopi USU',
            'address' => 'Jl. Dr. Mansyur No. 9',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $order1 = FotocopyOrder::create([
            'order_code' => 'ORD-TEST-QRIS-1',
            'store_id' => $store->id,
            'customer_name' => 'Mahasiswa 1',
            'payment_method' => 'qris',
            'total_price' => 20000,
        ]);

        $order2 = FotocopyOrder::create([
            'order_code' => 'ORD-TEST-QRIS-2',
            'store_id' => $store->id,
            'customer_name' => 'Mahasiswa 2',
            'payment_method' => 'qris',
            'total_price' => 20000,
        ]);

        $manager = app(PaymentManager::class);
        $res1 = $manager->process($order1, 'qris');
        $res2 = $manager->process($order2, 'qris');

        $this->assertTrue($res1->success);
        $this->assertTrue($res2->success);

        $payload1 = $order1->fresh()->payment_payload;
        $payload2 = $order2->fresh()->payment_payload;

        // Payloads must be unique even with the exact same price and store
        $this->assertNotEquals($payload1['qris_payload'], $payload2['qris_payload']);
        $this->assertStringContainsString('000201', $payload1['qris_payload']);
        $this->assertStringContainsString('ID.CO.QRIS.WWW', $payload1['qris_payload']);

        // Check SVG vector format
        $this->assertStringContainsString('<svg', $payload1['qr_code_svg']);
        $this->assertStringContainsString('</svg>', $payload1['qr_code_svg']);
        $this->assertNotEmpty($payload1['expires_at']);
    }

    public function test_bank_transfer_strategy_uses_poc_static_va_and_records_channel(): void
    {
        $store = FotocopyStore::first() ?? FotocopyStore::create([
            'name' => 'Berkah Fotokopi USU',
            'address' => 'Jl. Dr. Mansyur No. 9',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $orderBca = FotocopyOrder::create([
            'order_code' => 'ORD-TEST-VA-BCA',
            'store_id' => $store->id,
            'customer_name' => 'Ani Wijaya',
            'payment_method' => 'bank_transfer',
            'total_price' => 35000,
        ]);

        $orderMandiri = FotocopyOrder::create([
            'order_code' => 'ORD-TEST-VA-MDR',
            'store_id' => $store->id,
            'customer_name' => 'Bambang',
            'payment_method' => 'bank_transfer',
            'total_price' => 50000,
        ]);

        $manager = app(PaymentManager::class);
        $resBca = $manager->process($orderBca, 'bank_transfer', ['payment_channel' => 'BCA']);
        $resMandiri = $manager->process($orderMandiri, 'bank_transfer', ['payment_channel' => 'Mandiri']);

        $staticVa = config('payment.bank_transfer.static_bank_code', '88019920260001');

        $this->assertEquals('BCA', $orderBca->fresh()->payment_channel);
        $this->assertEquals($staticVa, $orderBca->fresh()->payment_payload['account_number']);

        $this->assertEquals('Mandiri', $orderMandiri->fresh()->payment_channel);
        $this->assertEquals($staticVa, $orderMandiri->fresh()->payment_payload['account_number']);
    }

    public function test_simulation_endpoint_marks_order_as_paid(): void
    {
        $store = FotocopyStore::first() ?? FotocopyStore::create([
            'name' => 'Berkah Fotokopi USU',
            'address' => 'Jl. Dr. Mansyur No. 9',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $order = FotocopyOrder::create([
            'order_code' => 'ORD-TEST-SIM-1',
            'store_id' => $store->id,
            'customer_name' => 'Tes Simulasi',
            'payment_method' => 'qris',
            'payment_status' => 'pending',
            'order_status' => 'Menunggu',
            'total_price' => 12000,
        ]);

        app(PaymentManager::class)->process($order, 'qris');

        $response = $this->postJson(route('fotocopy.orders.simulate-payment', $order->order_code));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'payment_status' => 'paid',
            'order_status' => 'Diproses',
        ]);

        $this->assertTrue($order->fresh()->isPaid());
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_store_order_route_creates_order_and_redirects_to_payment(): void
    {
        $place = PhotocopyPlace::first() ?? PhotocopyPlace::create([
            'name' => 'Mitra Fotokopi Pintu 1 USU',
            'address' => 'Jl. Universitas No. 1, Padang Bulan',
            'latitude' => 3.5629,
            'longitude' => 98.6562,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('fotocopy.order.store', $place->id), [
            'customer_name' => 'Dewi Sartika',
            'customer_phone' => '081399887766',
            'notes' => 'Tolong kertas HVS 80gr',
            'payment_method' => 'bank_transfer',
            'payment_channel' => 'BNI',
            'items' => [
                [
                    'name' => 'Print Warna A4',
                    'price' => 1500,
                    'quantity' => 10,
                ],
                [
                    'name' => 'Jilid Mika',
                    'price' => 5000,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['order_code']);
        $this->assertNotEmpty($data['payment_url']);

        $order = FotocopyOrder::where('order_code', $data['order_code'])->first();
        $this->assertNotNull($order);
        $this->assertEquals(20000, $order->total_price);
        $this->assertEquals('bank_transfer', $order->payment_method);
        $this->assertEquals('BNI', $order->payment_channel);

        // Access the payment instructions page
        $pageResponse = $this->get(route('fotocopy.orders.payment', $order->order_code));
        $pageResponse->assertStatus(200);
        $pageResponse->assertSee($order->order_code);
        $pageResponse->assertSee('88019920260001');
    }
}
