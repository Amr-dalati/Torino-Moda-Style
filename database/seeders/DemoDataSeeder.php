<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryArea;
use App\Models\DeliveryRegion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Local/testing demo catalog, customers, stock, and sample commerce data.
 *
 * Run: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    /** @var array<string, DeliveryArea> */
    private array $areasByCode = [];

    /** @var array<string, Color> */
    private array $colorsByCode = [];

    /** @var array<string, Size> */
    private array $sizesByCode = [];

    /** @var array<string, Category> */
    private array $categoriesByCode = [];

    /** @var array<string, Brand> */
    private array $brandsByCode = [];

    /** @var array<string, Warehouse> */
    private array $warehousesByCode = [];

    /** @var list<ProductVariant> */
    private array $allVariants = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoDataSeeder skipped: only allowed in local or testing environments.');

            return;
        }

        DB::transaction(function () {
            $this->seedAdminUser();
            $this->seedDeliveryAreas();
            $this->seedCatalogMasters();
            $this->seedWarehouses();
            $this->seedProductsAndVariants();
            $this->seedStockLevels();
            $customers = $this->seedCustomers();
            $this->seedCustomerAddresses($customers);
            $this->seedDemoCart($customers[0]);
            $this->seedDemoOrders($customers);
        });

        $this->printSummary();
    }

    private function seedAdminUser(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@torinomodastyle.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => UserRole::Admin,
                'phone' => null,
                'is_active' => true,
            ],
        );
    }

    private function seedDeliveryAreas(): void
    {
        $oman = DeliveryRegion::query()->updateOrCreate(
            ['code' => 'OMAN'],
            [
                'name_en' => 'Oman',
                'name_ar' => 'عُمان',
                'is_active' => true,
            ],
        );

        $areas = [
            ['code' => 'MUSCAT', 'name_en' => 'Muscat', 'name_ar' => 'مسقط', 'delivery_fee' => 2.00],
            ['code' => 'BARKA', 'name_en' => 'Barka', 'name_ar' => 'بركاء', 'delivery_fee' => 3.50],
            ['code' => 'SEEB', 'name_en' => 'Seeb', 'name_ar' => 'السيب', 'delivery_fee' => 2.50],
            ['code' => 'SOHAR', 'name_en' => 'Sohar', 'name_ar' => 'صحار', 'delivery_fee' => 5.00],
            ['code' => 'NIZWA', 'name_en' => 'Nizwa', 'name_ar' => 'نزوى', 'delivery_fee' => 4.50],
        ];

        foreach ($areas as $area) {
            $this->areasByCode[$area['code']] = DeliveryArea::query()->updateOrCreate(
                ['code' => $area['code']],
                [
                    'delivery_region_id' => $oman->id,
                    'name_en' => $area['name_en'],
                    'name_ar' => $area['name_ar'],
                    'delivery_fee' => $area['delivery_fee'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedCatalogMasters(): void
    {
        foreach ([
            ['code' => 'WOMEN_SHOES', 'name_en' => 'Women Shoes', 'name_ar' => 'أحذية نسائية'],
            ['code' => 'HANDBAGS', 'name_en' => 'Handbags', 'name_ar' => 'حقائب يد'],
            ['code' => 'ACCESSORIES', 'name_en' => 'Accessories', 'name_ar' => 'إكسسوارات'],
            ['code' => 'KIDS_SHOES', 'name_en' => 'Kids Shoes', 'name_ar' => 'أحذية أطفال'],
        ] as $row) {
            $this->categoriesByCode[$row['code']] = Category::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name_en' => $row['name_en'],
                    'name_ar' => $row['name_ar'],
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['code' => 'TORINO', 'name' => 'Torino Moda'],
            ['code' => 'ELEGANCE', 'name' => 'Elegance'],
            ['code' => 'LUXE', 'name' => 'Luxe Line'],
            ['code' => 'KIDS_STEP', 'name' => 'Kids Step'],
        ] as $row) {
            $this->brandsByCode[$row['code']] = Brand::query()->updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'is_active' => true],
            );
        }

        foreach ([
            ['code' => 'BLACK', 'name_en' => 'Black', 'name_ar' => 'أسود'],
            ['code' => 'WHITE', 'name_en' => 'White', 'name_ar' => 'أبيض'],
            ['code' => 'BEIGE', 'name_en' => 'Beige', 'name_ar' => 'بيج'],
            ['code' => 'BROWN', 'name_en' => 'Brown', 'name_ar' => 'بني'],
            ['code' => 'RED', 'name_en' => 'Red', 'name_ar' => 'أحمر'],
            ['code' => 'PINK', 'name_en' => 'Pink', 'name_ar' => 'وردي'],
            ['code' => 'GOLD', 'name_en' => 'Gold', 'name_ar' => 'ذهبي'],
        ] as $row) {
            $this->colorsByCode[$row['code']] = Color::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name_en' => $row['name_en'],
                    'name_ar' => $row['name_ar'],
                    'is_active' => true,
                ],
            );
        }

        foreach ([
            ['code' => '36', 'name' => '36', 'sort_order' => 36],
            ['code' => '37', 'name' => '37', 'sort_order' => 37],
            ['code' => '38', 'name' => '38', 'sort_order' => 38],
            ['code' => '39', 'name' => '39', 'sort_order' => 39],
            ['code' => '40', 'name' => '40', 'sort_order' => 40],
            ['code' => '41', 'name' => '41', 'sort_order' => 41],
            ['code' => 'ONE', 'name' => 'One Size', 'sort_order' => 100],
        ] as $row) {
            $this->sizesByCode[$row['code']] = Size::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedWarehouses(): void
    {
        $this->warehousesByCode['MAIN'] = Warehouse::query()->updateOrCreate(
            ['code' => 'MAIN_WH'],
            ['name' => 'Main Warehouse', 'is_active' => true],
        );

        $this->warehousesByCode['SHOWROOM'] = Warehouse::query()->updateOrCreate(
            ['code' => 'SHOWROOM_WH'],
            ['name' => 'Showroom Warehouse', 'is_active' => true],
        );
    }

    private function seedProductsAndVariants(): void
    {
        $definitions = $this->productDefinitions();
        $variantCounter = 1;

        foreach ($definitions as $index => $definition) {
            $product = Product::query()->updateOrCreate(
                ['product_code' => $definition['product_code']],
                [
                    'barcode' => $definition['barcode'],
                    'name_en' => $definition['name_en'],
                    'name_ar' => $definition['name_ar'],
                    'category_id' => $this->categoriesByCode[$definition['category']]->id,
                    'brand_id' => $this->brandsByCode[$definition['brand']]->id,
                    'sale_price' => $definition['base_price'],
                    'is_active' => true,
                ],
            );

            foreach ($definition['variants'] as $variantDef) {
                $color = $this->colorsByCode[$variantDef['color']];
                $size = $this->sizesByCode[$variantDef['size']];
                $sku = sprintf('%s-%s-%s', $definition['product_code'], $color->code, $size->code);
                $barcode = sprintf('968%05d%04d', $index + 1, $variantCounter);
                $variantCounter++;

                $variant = ProductVariant::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'color_id' => $color->id,
                        'size_id' => $size->id,
                    ],
                    [
                        'sku' => $sku,
                        'barcode' => $barcode,
                        'sale_price' => $variantDef['sale_price'] ?? $definition['base_price'],
                        'is_active' => $variantDef['is_active'] ?? true,
                    ],
                );

                $variant->setRelation('product', $product);
                $variant->setRelation('color', $color);
                $variant->setRelation('size', $size);
                $this->allVariants[] = $variant;
            }
        }
    }

    /**
     * @return list<array{
     *     product_code: string,
     *     barcode: string,
     *     name_en: string,
     *     name_ar: string,
     *     category: string,
     *     brand: string,
     *     base_price: float,
     *     variants: list<array{color: string, size: string, sale_price?: float, is_active?: bool}>
     * }>
     */
    private function productDefinitions(): array
    {
        $shoeColors = ['BLACK', 'WHITE', 'BEIGE', 'BROWN', 'RED', 'PINK'];
        $shoeSizes = ['36', '37', '38', '39', '40', '41'];
        $bagColors = ['BLACK', 'BEIGE', 'BROWN', 'GOLD'];
        $accessoryColors = ['BLACK', 'GOLD', 'PINK'];
        $kidsColors = ['PINK', 'WHITE', 'RED'];

        $makeShoeVariants = function (array $colors, array $sizes, float $base): array {
            $variants = [];
            foreach (array_slice($colors, 0, 3) as $color) {
                foreach (array_slice($sizes, 0, 3) as $size) {
                    $variants[] = ['color' => $color, 'size' => $size, 'sale_price' => $base];
                }
            }

            return $variants;
        };

        $makeBagVariants = function (array $colors, float $base, bool $withInactive = false): array {
            $variants = [];
            foreach ($colors as $i => $color) {
                $variants[] = [
                    'color' => $color,
                    'size' => 'ONE',
                    'sale_price' => $base + ($i * 2),
                    'is_active' => ! ($withInactive && $i === count($colors) - 1),
                ];
            }

            return $variants;
        };

        $shoes = [
            ['WS-HEEL-CLASSIC', 'Classic Heeled Pumps', 'حذاء كعب كلاسيكي', 34.90],
            ['WS-LOAFER-SOFT', 'Soft Leather Loafers', 'موكاسين جلد ناعم', 29.90],
            ['WS-SNEAKER-CITY', 'City Comfort Sneakers', 'سنيكرز مريح', 27.50],
            ['WS-SANDAL-SUMMER', 'Summer Strap Sandals', 'صندل صيفي', 22.00],
            ['WS-BOOT-ANKLE', 'Ankle Boot Elegance', 'بوت كاحل أنيق', 39.90],
            ['WS-FLAT-BALLET', 'Ballet Flat Essentials', 'باليه فلات أساسي', 24.50],
            ['WS-MULE-CHIC', 'Chic Open Mule', 'ميول مفتوح عصري', 26.00],
            ['WS-WEDGE-RESORT', 'Resort Wedge Sandal', 'صندل ويدج ريزورت', 31.00],
            ['WS-PLATFORM-NIGHT', 'Night Platform Heels', 'كعب منصة ليلي', 36.50],
            ['WS-ESPADRILLE', 'Raffia Espadrille', 'إسبادريل قش', 23.50],
        ];

        $bags = [
            ['HB-TOTE-DAILY', 'Daily Shopper Tote', 'حقيبة تسوق يومية', 45.00],
            ['HB-CROSS-MINI', 'Mini Crossbody Bag', 'حقيبة كروس صغيرة', 38.00],
            ['HB-SATCHEL-WORK', 'Work Satchel Bag', 'حقيبة ساتشيل عمل', 52.00],
            ['HB-CLUTCH-EVENING', 'Evening Clutch', 'حقيبة سهرة', 29.00],
            ['HB-BUCKET-TREND', 'Trend Bucket Bag', 'حقيبة باكيت', 41.00],
        ];

        $accessories = [
            ['AC-SCARF-SILK', 'Silk Touch Scarf', 'وشاح حريري', 15.00],
            ['AC-BELT-LEATHER', 'Leather Waist Belt', 'حزام جلد', 18.00],
            ['AC-WALLET-COMPACT', 'Compact Card Wallet', 'محفظة بطاقات', 21.00],
        ];

        $kids = [
            ['KS-RUNNER-FUN', 'Kids Fun Runner', 'حذاء جري أطفال', 19.90],
            ['KS-SCHOOL-DURABLE', 'Durable School Shoe', 'حذاء مدرسي متين', 22.50],
        ];

        $definitions = [];
        $productNo = 1;

        foreach ($shoes as [$code, $en, $ar, $price]) {
            $definitions[] = [
                'product_code' => $code,
                'barcode' => sprintf('968P%05d', $productNo++),
                'name_en' => $en,
                'name_ar' => $ar,
                'category' => 'WOMEN_SHOES',
                'brand' => 'TORINO',
                'base_price' => $price,
                'variants' => $makeShoeVariants($shoeColors, $shoeSizes, $price),
            ];
        }

        foreach ($bags as $i => [$code, $en, $ar, $price]) {
            $definitions[] = [
                'product_code' => $code,
                'barcode' => sprintf('968P%05d', $productNo++),
                'name_en' => $en,
                'name_ar' => $ar,
                'category' => 'HANDBAGS',
                'brand' => 'LUXE',
                'base_price' => $price,
                'variants' => $makeBagVariants($bagColors, $price, $i === 0),
            ];
        }

        foreach ($accessories as [$code, $en, $ar, $price]) {
            $definitions[] = [
                'product_code' => $code,
                'barcode' => sprintf('968P%05d', $productNo++),
                'name_en' => $en,
                'name_ar' => $ar,
                'category' => 'ACCESSORIES',
                'brand' => 'ELEGANCE',
                'base_price' => $price,
                'variants' => $makeBagVariants($accessoryColors, $price),
            ];
        }

        foreach ($kids as [$code, $en, $ar, $price]) {
            $definitions[] = [
                'product_code' => $code,
                'barcode' => sprintf('968P%05d', $productNo++),
                'name_en' => $en,
                'name_ar' => $ar,
                'category' => 'KIDS_SHOES',
                'brand' => 'KIDS_STEP',
                'base_price' => $price,
                'variants' => $makeShoeVariants($kidsColors, ['36', '37', '38'], $price),
            ];
        }

        return $definitions;
    }

    private function seedStockLevels(): void
    {
        $main = $this->warehousesByCode['MAIN'];
        $showroom = $this->warehousesByCode['SHOWROOM'];

        foreach ($this->allVariants as $index => $variant) {
            $profile = match ($index % 10) {
                0 => ['main' => 0, 'showroom' => 0],
                1 => ['main' => 2, 'showroom' => 1],
                2 => ['main' => 3, 'showroom' => 0],
                default => ['main' => 25 + ($index % 20), 'showroom' => 5 + ($index % 8)],
            };

            StockLevel::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $main->id,
                ],
                ['quantity_on_hand' => $profile['main'], 'quantity_reserved' => 0],
            );

            StockLevel::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $showroom->id,
                ],
                ['quantity_on_hand' => $profile['showroom'], 'quantity_reserved' => 0],
            );
        }
    }

    /**
     * @return array<int, Customer>
     */
    private function seedCustomers(): array
    {
        $rows = [
            ['phone' => '96890000001', 'name' => 'Demo Customer One', 'email' => 'demo1@torinomodastyle.local'],
            ['phone' => '96890000002', 'name' => 'Demo Customer Two', 'email' => 'demo2@torinomodastyle.local'],
            ['phone' => '96890000003', 'name' => 'Demo Customer Three', 'email' => 'demo3@torinomodastyle.local'],
        ];

        $customers = [];
        foreach ($rows as $row) {
            $customers[] = Customer::query()->updateOrCreate(
                ['phone' => $row['phone']],
                [
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'is_active' => true,
                ],
            );
        }

        return $customers;
    }

    /**
     * @param  array<int, Customer>  $customers
     */
    private function seedCustomerAddresses(array $customers): void
    {
        $areaCodes = ['MUSCAT', 'BARKA', 'SEEB', 'SOHAR', 'NIZWA'];

        foreach ($customers as $i => $customer) {
            $area = $this->areasByCode[$areaCodes[$i % count($areaCodes)]];

            CustomerAddress::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'label' => 'Home',
                ],
                [
                    'delivery_area_id' => $area->id,
                    'recipient_name' => $customer->name,
                    'recipient_phone' => $customer->phone,
                    'address_line1' => sprintf('Building %d, Street %d', $i + 10, $i + 1),
                    'address_line2' => 'Near city center',
                    'city' => $area->name_en,
                    'area_name' => $area->name_en,
                    'postal_code' => sprintf('100%d', $i + 1),
                    'is_default' => true,
                ],
            );
        }
    }

    private function seedDemoCart(Customer $customer): void
    {
        $cart = Cart::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'status' => 'active',
            ],
            [
                'currency' => config('app.currency'),
                'subtotal' => 0,
            ],
        );

        $variantA = $this->findVariantWithStock(minQty: 2);
        $variantB = $this->findVariantWithStock(minQty: 1, skipVariantId: $variantA->id);

        $items = [
            ['variant' => $variantA, 'qty' => 2],
            ['variant' => $variantB, 'qty' => 1],
        ];

        $subtotal = 0.0;
        foreach ($items as $item) {
            $unit = (float) ($item['variant']->sale_price ?? $item['variant']->product->sale_price);
            $line = round($unit * $item['qty'], 2);

            CartItem::query()->updateOrCreate(
                [
                    'cart_id' => $cart->id,
                    'product_variant_id' => $item['variant']->id,
                ],
                [
                    'quantity' => $item['qty'],
                    'unit_price_snapshot' => $unit,
                    'line_total' => $line,
                ],
            );

            $subtotal += $line;
        }

        $cart->forceFill(['subtotal' => round($subtotal, 2)])->save();
    }

    /**
     * @param  array<int, Customer>  $customers
     */
    private function seedDemoOrders(array $customers): void
    {
        $paidCustomer = $customers[1];
        $shippedCustomer = $customers[2];

        $addressPaid = $paidCustomer->addresses()->where('is_default', true)->firstOrFail();
        $addressShipped = $shippedCustomer->addresses()->where('is_default', true)->firstOrFail();

        $lineVariantPaid = $this->findVariantWithStock(minQty: 1);
        $lineVariantShipped = $this->findVariantWithStock(minQty: 2, skipVariantId: $lineVariantPaid->id);

        $this->createDemoOrder(
            orderNumber: 'DEMO-PAID-0001',
            customer: $paidCustomer,
            address: $addressPaid,
            variants: [['variant' => $lineVariantPaid, 'qty' => 1]],
            orderStatus: 'paid',
            paymentStatus: 'paid',
            paymentPaid: true,
        );

        $this->createDemoOrder(
            orderNumber: 'DEMO-SHIPPED-0001',
            customer: $shippedCustomer,
            address: $addressShipped,
            variants: [['variant' => $lineVariantShipped, 'qty' => 2]],
            orderStatus: 'shipped',
            paymentStatus: 'paid',
            paymentPaid: true,
        );
    }

    /**
     * @param  list<array{variant: ProductVariant, qty: int}>  $variants
     */
    private function createDemoOrder(
        string $orderNumber,
        Customer $customer,
        CustomerAddress $address,
        array $variants,
        string $orderStatus,
        string $paymentStatus,
        bool $paymentPaid,
    ): void {
        $area = $address->deliveryArea ?? $this->areasByCode['MUSCAT'];
        $area->loadMissing('region');

        $subtotal = 0.0;
        $orderItemsPayload = [];

        foreach ($variants as $row) {
            /** @var ProductVariant $variant */
            $variant = $row['variant'];
            $variant->loadMissing(['product', 'color', 'size']);
            $qty = $row['qty'];
            $unit = (float) ($variant->sale_price ?? $variant->product->sale_price);
            $line = round($unit * $qty, 2);
            $subtotal += $line;

            $orderItemsPayload[] = [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_code' => $variant->product->product_code,
                'variant_sku' => $variant->sku,
                'variant_barcode' => $variant->barcode,
                'product_name_en' => $variant->product->name_en,
                'product_name_ar' => $variant->product->name_ar,
                'color_code' => $variant->color?->code,
                'size_code' => $variant->size?->code,
                'quantity' => $qty,
                'unit_price_snapshot' => $unit,
                'line_total' => $line,
            ];
        }

        $deliveryFee = (float) $area->delivery_fee;
        $total = round($subtotal + $deliveryFee, 2);

        $order = Order::query()->updateOrCreate(
            ['order_number' => $orderNumber],
            [
                'customer_id' => $customer->id,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'discount_total' => 0,
                'total' => $total,
                'currency' => config('app.currency'),
                'shipping_label' => $address->label,
                'shipping_recipient_name' => $address->recipient_name,
                'shipping_recipient_phone' => $address->recipient_phone,
                'shipping_address_line1' => $address->address_line1,
                'shipping_address_line2' => $address->address_line2,
                'shipping_city' => $address->city,
                'shipping_area_name' => $address->area_name,
                'shipping_postal_code' => $address->postal_code,
                'shipping_delivery_region_code' => $area->region?->code,
                'shipping_delivery_area_code' => $area->code,
                'shipping_delivery_area_id' => $area->id,
                'customer_address_id' => $address->id,
                'cart_id' => null,
            ],
        );

        foreach ($orderItemsPayload as $payload) {
            OrderItem::query()->updateOrCreate(
                [
                    'order_id' => $order->id,
                    'product_variant_id' => $payload['product_variant_id'],
                ],
                $payload,
            );
        }

        Payment::query()->updateOrCreate(
            ['merchant_reference' => 'DEMO-PAY-'.$orderNumber],
            [
                'order_id' => $order->id,
                'provider' => 'mock',
                'method' => 'card',
                'amount' => $total,
                'currency' => config('app.currency'),
                'status' => $paymentPaid ? 'paid' : 'pending',
                'paid_at' => $paymentPaid ? now() : null,
                'checkout_url' => null,
            ],
        );
    }

    private function findVariantWithStock(int $minQty, ?int $skipVariantId = null): ProductVariant
    {
        foreach ($this->allVariants as $variant) {
            if ($skipVariantId !== null && $variant->id === $skipVariantId) {
                continue;
            }

            if (! $variant->is_active) {
                continue;
            }

            $available = StockLevel::query()
                ->where('product_variant_id', $variant->id)
                ->get()
                ->sum(fn (StockLevel $level) => (float) $level->quantity_on_hand - (float) $level->quantity_reserved);

            if ($available >= $minQty) {
                $variant->loadMissing('product');

                return $variant;
            }
        }

        throw new \RuntimeException('No demo variant with sufficient stock found.');
    }

    private function printSummary(): void
    {
        if (! $this->command) {
            return;
        }

        $this->command->info('Demo data seeded successfully.');
        $this->command->line('Admin: admin@torinomodastyle.com / '.self::DEMO_PASSWORD);
        $this->command->line('Customers: 96890000001, 96890000002, 96890000003 / '.self::DEMO_PASSWORD);
        $this->command->line('Products: '.Product::query()->count());
        $this->command->line('Variants: '.ProductVariant::query()->count());
        $this->command->line('Stock levels: '.StockLevel::query()->count());
        $this->command->line('Delivery areas: '.DeliveryArea::query()->whereIn('code', array_keys($this->areasByCode))->count());
    }
}
