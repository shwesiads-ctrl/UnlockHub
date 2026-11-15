<?php

namespace App\Services\Suppliers;

use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * DHRU Fusion client API.
 *
 * POST {url}/api/index.php  with form fields:
 *   username, apiaccesskey, action, requestformat=JSON,
 *   parameters = <PARAMETERS><ID>…</ID><IMEI>…</IMEI></PARAMETERS>
 * Reply: {"SUCCESS":[{…}]} or {"ERROR":[{"MESSAGE":"…"}]}
 */
class DhruFusionDriver implements SupplierDriver
{
    /** Order field keys that DHRU accepts as their own parameters. Everything else goes into CUSTOMFIELD. */
    private const NATIVE_PARAMS = [
        'imei' => 'IMEI', 'sn' => 'SN', 'serial' => 'SN', 'serial_number' => 'SN',
        'modelid' => 'MODELID', 'model_id' => 'MODELID', 'providerid' => 'PROVIDERID', 'provider_id' => 'PROVIDERID',
        'networkid' => 'NETWORKID', 'network_id' => 'NETWORKID', 'mep' => 'MEP', 'pin' => 'PIN', 'kbh' => 'KBH',
        'prd' => 'PRD', 'type' => 'TYPE', 'reference' => 'REFERENCE', 'locks' => 'LOCKS', 'qnt' => 'QNT', 'quantity' => 'QNT',
    ];

    /** DHRU order status codes. */
    private const STATUS = [0 => 'pending', 1 => 'in_process', 2 => 'in_process', 3 => 'rejected', 4 => 'success'];

    public function accountInfo(Supplier $supplier): array
    {
        $r = $this->call($supplier, 'accountinfo');
        $row = $r['SUCCESS'][0] ?? [];
        $info = $row['AccoutInfo'] ?? $row['AccountInfo'] ?? $row['ACCOUNTINFO'] ?? [];

        return [
            'credit' => (float) str_replace(',', '', (string) ($info['credit'] ?? $info['CREDIT'] ?? 0)),
            'currency' => $info['currency'] ?? $info['CURRENCY'] ?? null,
            'email' => $info['mail'] ?? $info['MAIL'] ?? $info['email'] ?? null,
        ];
    }

    public function serviceList(Supplier $supplier): array
    {
        $r = $this->call($supplier, 'imeiservicelist');
        $groups = $r['SUCCESS'][0]['LIST'] ?? [];
        $out = [];

        foreach ($groups as $groupKey => $group) {
            $groupName = (string) ($group['GROUPNAME'] ?? $groupKey);
            $groupType = $group['GROUPTYPE'] ?? null;

            foreach (($group['SERVICES'] ?? []) as $sid => $s) {
                $requires = [];
                foreach ($s as $k => $v) {
                    if (is_string($v) && strcasecmp($v, 'Required') === 0) {
                        $requires[] = Str::after($k, 'Requires.');
                    }
                }

                $type = strtolower((string) ($s['SERVICETYPE'] ?? $groupType ?? 'imei'));

                $out[] = [
                    'id' => (string) ($s['SERVICEID'] ?? $sid),
                    'name' => trim(html_entity_decode((string) ($s['SERVICENAME'] ?? 'Service '.$sid))),
                    'group' => trim(html_entity_decode($groupName)),
                    'credit' => (float) str_replace(',', '', (string) ($s['CREDIT'] ?? 0)),
                    'time' => isset($s['TIME']) ? trim(strip_tags((string) $s['TIME'])) : null,
                    'type' => in_array($type, ['imei', 'server', 'remote'], true) ? $type : 'imei',
                    'info' => isset($s['INFO']) ? Str::limit(trim(strip_tags(html_entity_decode((string) $s['INFO']))), 2000) : null,
                    'requires' => $requires,
                ];
            }
        }

        return $out;
    }

    public function placeOrder(Supplier $supplier, Order $order): string
    {
        $order->loadMissing('service');
        $serviceId = $order->service?->supplier_service_id;

        if (! $serviceId) {
            throw new SupplierException('Service has no supplier service ID.');
        }

        $params = ['ID' => $serviceId];
        $custom = [];

        foreach ((array) $order->input as $key => $value) {
            $native = self::NATIVE_PARAMS[strtolower($key)] ?? null;
            if ($native) {
                $params[$native] = $value;
            } else {
                $custom[$key] = $value;
            }
        }

        if ($custom) {
            $params['CUSTOMFIELD'] = base64_encode(json_encode($custom));
        }

        // Our order number helps both sides find the order later
        $params['REFERENCE'] ??= $order->order_no;

        $r = $this->call($supplier, 'placeimeiorder', $params);
        $ref = $r['SUCCESS'][0]['REFERENCEID'] ?? $r['SUCCESS'][0]['REFERENCE'] ?? null;

        if (! $ref) {
            throw new SupplierException('Supplier accepted the request but returned no order reference.');
        }

        return (string) $ref;
    }

    public function checkOrder(Supplier $supplier, Order $order): array
    {
        $r = $this->call($supplier, 'getimeiorder', ['ID' => $order->supplier_order_id]);
        $row = $r['SUCCESS'][0] ?? [];

        $status = self::STATUS[(int) ($row['STATUS'] ?? 0)] ?? 'pending';
        $code = $this->clean($row['CODE'] ?? null);
        $comments = $this->clean($row['COMMENTS'] ?? null);

        return [
            'status' => $status,
            'result' => match ($status) {
                'success' => $code ?: ($comments ?: 'Done'),
                'rejected' => $comments ?: ($code ?: 'Rejected by supplier.'),
                default => null,
            },
        ];
    }

    /** @throws SupplierException */
    public function call(Supplier $supplier, string $action, array $params = []): array
    {
        $xml = '<PARAMETERS>';
        foreach ($params as $k => $v) {
            $tag = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string) $k));
            $xml .= "<{$tag}>".htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</{$tag}>";
        }
        $xml .= '</PARAMETERS>';

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('unlockhub.api.timeout_seconds', 30))
                ->post($this->endpoint($supplier), [
                    'username' => $supplier->api_username,
                    'apiaccesskey' => $supplier->api_key,
                    'action' => $action,
                    'requestformat' => 'JSON',
                    'parameters' => $xml,
                ]);
        } catch (ConnectionException $e) {
            throw new SupplierException('Could not connect to supplier (timeout or DNS): '.Str::limit($e->getMessage(), 200));
        }

        if ($response->failed()) {
            throw new SupplierException("Supplier returned HTTP {$response->status()}.");
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new SupplierException('Supplier reply is not valid JSON: '.Str::limit(strip_tags($response->body()), 150));
        }

        if (! empty($json['ERROR'])) {
            $msg = $json['ERROR'][0]['MESSAGE'] ?? $json['ERROR'][0]['message'] ?? 'Unknown supplier error';
            throw new SupplierException((string) $msg);
        }

        return $json;
    }

    private function endpoint(Supplier $supplier): string
    {
        $url = rtrim((string) $supplier->api_url, '/');

        return str_ends_with(strtolower($url), '.php') ? $url : $url.'/api/index.php';
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = preg_replace('#<br\s*/?>#i', "\n", (string) $value);
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '' ? null : $text;
    }
}
