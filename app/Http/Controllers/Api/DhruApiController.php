<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\OrderService;
use App\Support\OrderInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * DHRU Fusion compatible API so resellers can connect their own DHRU site to us.
 *
 * POST {site}/api/index.php
 *   username, apiaccesskey, action, requestformat=JSON, parameters=<PARAMETERS>…</PARAMETERS>
 */
class DhruApiController extends Controller
{
    public const VERSION = '6.1';

    /** DHRU parameter → our order field keys it can fill. */
    private const NATIVE = [
        'IMEI' => ['imei'], 'SN' => ['sn', 'serial', 'serial_number'], 'MODELID' => ['modelid', 'model_id'],
        'PROVIDERID' => ['providerid', 'provider_id'], 'NETWORKID' => ['networkid', 'network_id'],
        'MEP' => ['mep'], 'PIN' => ['pin'], 'KBH' => ['kbh'], 'PRD' => ['prd'], 'TYPE' => ['type'],
        'REFERENCE' => ['reference'], 'LOCKS' => ['locks'], 'QNT' => ['qnt', 'quantity'],
    ];

    /** How DHRU names the "required field" flags in service lists. */
    private const REQUIRES_LABEL = [
        'SN' => 'Requires.SN', 'MODELID' => 'Requires.Mobile', 'NETWORKID' => 'Requires.Network',
        'PROVIDERID' => 'Requires.Provider', 'PIN' => 'Requires.PIN', 'KBH' => 'Requires.KBH', 'MEP' => 'Requires.MEP',
        'PRD' => 'Requires.PRD', 'TYPE' => 'Requires.Type', 'LOCKS' => 'Requires.Locks',
        'REFERENCE' => 'Requires.Reference', 'QNT' => 'Requires.QNT',
    ];

    public function __construct(private OrderService $orders) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->authenticate($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $params = $this->parameters((string) $request->input('parameters', ''));

        try {
            return match (strtolower((string) $request->input('action'))) {
                'accountinfo' => $this->accountInfo($user),
                'imeiservicelist', 'serverservicelist', 'servicelist' => $this->serviceList($user),
                'getimeiservicedetails' => $this->serviceDetails($user, $params),
                'placeimeiorder' => $this->placeOrder($user, $params),
                'getimeiorder' => $this->getOrder($user, $params),
                default => $this->error('Invalid action'),
            };
        } catch (Throwable $e) {
            report($e);

            return $this->error('Server error, please try again');
        }
    }

    private function authenticate(Request $request): User|JsonResponse
    {
        $login = trim((string) $request->input('username'));
        $key = trim((string) $request->input('apiaccesskey'));
        $limiter = 'dhru-api-auth|'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiter, 20)) {
            return $this->error('Too many failed attempts, try later');
        }

        $user = $login !== '' && $key !== ''
            ? User::where(filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username', $login)->first()
            : null;

        if (! $user || ! $user->api_enabled || ! $user->api_key_hash || ! hash_equals($user->api_key_hash, hash('sha256', $key))) {
            RateLimiter::hit($limiter, 600);

            return $this->error('Authentication Failed');
        }

        if (! $user->isActive()) {
            return $this->error('Account suspended');
        }

        $ips = $user->apiIpList();
        if ($ips && ! in_array($request->ip(), $ips, true)) {
            return $this->error('IP '.$request->ip().' not allowed');
        }

        RateLimiter::clear($limiter);

        if (! $user->api_last_used_at || $user->api_last_used_at->lt(now()->subMinute())) {
            $user->forceFill(['api_last_used_at' => now()])->saveQuietly();
        }

        return $user;
    }

    /** <PARAMETERS><ID>1</ID><IMEI>…</IMEI></PARAMETERS> → ['ID' => '1', 'IMEI' => '…'] */
    private function parameters(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '') {
            return [];
        }

        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);

        if ($doc === false) {
            return [];
        }

        $out = [];
        foreach ($doc->children() as $name => $value) {
            $out[strtoupper((string) $name)] = trim((string) $value);
        }

        return $out;
    }

    private function accountInfo(User $user): JsonResponse
    {
        return $this->success([
            'message' => 'Your Account Info',
            'AccoutInfo' => [
                'credit' => number_format(setting('catalog.price_currency', 'ks') === 'points' ? $user->points : (float) $user->balance, 2, '.', ''),
                'mail' => $user->email,
                'currency' => setting('catalog.price_currency', 'ks') === 'points' ? 'POINTS' : 'MMK',
            ],
        ]);
    }

    private function serviceList(User $user): JsonResponse
    {
        $groupId = $user->user_group_id ?? 0;

        $categories = ServiceCategory::active()->orderBy('type')->orderBy('sort')->orderBy('name')
            ->with(['services' => fn ($q) => $q->visible()->with(['fields', 'prices' => fn ($p) => $p->where('user_group_id', $groupId)])])
            ->get();

        $list = [];
        foreach ($categories as $cat) {
            if ($cat->services->isEmpty()) {
                continue;
            }
            $services = [];
            foreach ($cat->services as $s) {
                $services[$s->id] = $this->serviceRow($s, $user);
            }
            $list[$cat->name] = [
                'GROUPNAME' => $cat->name,
                'GROUPTYPE' => strtoupper($cat->type),
                'SERVICES' => $services,
            ];
        }

        return $this->success(['MESSAGE' => 'IMEI Service List', 'LIST' => $list]);
    }

    private function serviceDetails(User $user, array $p): JsonResponse
    {
        $service = $this->findService($user, $p['ID'] ?? null);
        if (! $service) {
            return $this->error('Service not found', ['ID' => $p['ID'] ?? null]);
        }

        return $this->success(['MESSAGE' => 'Service Details', 'LIST' => $this->serviceRow($service, $user)], ['ID' => $service->id]);
    }

    private function serviceRow(Service $s, User $user): array
    {
        $row = [
            'SERVICEID' => $s->id,
            'SERVICETYPE' => strtoupper($s->type),
            'SERVICENAME' => $s->name,
            'CREDIT' => number_format((float) $s->priceFor($user), 2, '.', ''),
            'TIME' => (string) $s->delivery_time,
            'INFO' => (string) $s->description,
        ];

        $custom = [];
        foreach ($s->fields as $f) {
            $native = $this->nativeFor($f->name);
            if ($native) {
                if ($native !== 'IMEI') {
                    $row[self::REQUIRES_LABEL[$native]] = $f->is_required ? 'Required' : 'None';
                }
            } else {
                $custom[] = [
                    'fieldname' => $f->name,
                    'fieldlabel' => $f->label,
                    'fieldtype' => $f->input_type,
                    'fieldoptions' => $f->options ? implode(',', $f->options) : '',
                    'required' => $f->is_required ? 'Required' : 'None',
                ];
            }
        }
        if ($custom) {
            $row['Requires.Custom'] = $custom;
        }

        return $row;
    }

    private function placeOrder(User $user, array $p): JsonResponse
    {
        $service = $this->findService($user, $p['ID'] ?? null);
        $echo = ['ID' => $p['ID'] ?? null, 'IMEI' => $p['IMEI'] ?? null];

        if (! $service) {
            return $this->error('Service Not Active', $echo);
        }

        $service->loadMissing('fields');
        $custom = [];
        if (! empty($p['CUSTOMFIELD'])) {
            $decoded = json_decode((string) base64_decode($p['CUSTOMFIELD'], true), true);
            $custom = is_array($decoded) ? array_change_key_case($decoded, CASE_LOWER) : [];
        }

        // Fill each of our fields from a native DHRU parameter or from CUSTOMFIELD
        $values = [];
        foreach ($service->fields as $f) {
            $native = $this->nativeFor($f->name);
            $values[$f->name] = (string) ($native && isset($p[$native]) ? $p[$native] : ($custom[strtolower($f->name)] ?? $custom[strtolower($f->label)] ?? ''));
            // Bulk fields take one value per API call
            if ($f->is_bulk_field) {
                $values[$f->name] = trim(strtok($values[$f->name], "\r\n") ?: '');
            }
        }

        try {
            $rows = OrderInput::rows($service, $values);
            $note = 'API'.(! empty($p['REFERENCE']) ? ' · ref '.Str::limit($p['REFERENCE'], 60, '') : '');
            // REFERENCE (if the client sends one) makes a resent request return the same order
            $order = $this->orders->place($user, $service, array_slice($rows, 0, 1), $note, [
                'key' => ! empty($p['REFERENCE']) ? 'api:'.$service->id.':'.Str::limit($p['REFERENCE'], 80, '') : null,
                'source' => 'api',
            ])->first();
        } catch (\App\Exceptions\DuplicateOrderException $e) {
            // API clients can't confirm "order anyway", so open duplicates are refused
            return $this->error('Duplicate: IMEI already in order '.$e->existing->first()->id.' ('.$e->existing->first()->statusLabel().')', $echo);
        } catch (ValidationException $e) {
            return $this->error(collect($e->errors())->flatten()->first() ?: 'Invalid parameters', $echo);
        } catch (InsufficientBalanceException) {
            return $this->error('Not enough credit', $echo);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), $echo);
        }

        return $this->success(['MESSAGE' => 'Order received', 'REFERENCEID' => $order->id], $echo);
    }

    private function getOrder(User $user, array $p): JsonResponse
    {
        $order = Order::where('user_id', $user->id)->whereKey((int) ($p['ID'] ?? 0))->first();

        if (! $order) {
            return $this->error('NoResultFound', ['ID' => $p['ID'] ?? null]);
        }

        $status = match ($order->status) {
            Order::PENDING => 0,
            Order::IN_PROCESS => 1,
            Order::SUCCESS => 4,
            default => 3, // rejected / cancelled
        };

        return $this->success([
            'IMEI' => $order->imei,
            'STATUS' => $status,
            'CODE' => $order->status === Order::SUCCESS ? (string) $order->result : '',
            'COMMENTS' => $status === 3 ? (string) $order->result : '',
        ], ['ID' => $order->id]);
    }

    private function findService(User $user, mixed $id): ?Service
    {
        return $id ? Service::visible()->with(['fields', 'prices' => fn ($p) => $p->where('user_group_id', $user->user_group_id ?? 0)])->find((int) $id) : null;
    }

    private function nativeFor(string $fieldKey): ?string
    {
        foreach (self::NATIVE as $param => $keys) {
            if (in_array(strtolower($fieldKey), $keys, true)) {
                return $param;
            }
        }

        return null;
    }

    private function success(array $row, array $extra = []): JsonResponse
    {
        return response()->json($extra + ['SUCCESS' => [$row], 'apiversion' => self::VERSION]);
    }

    private function error(string $message, array $extra = []): JsonResponse
    {
        return response()->json($extra + ['ERROR' => [['MESSAGE' => $message]], 'apiversion' => self::VERSION]);
    }
}
