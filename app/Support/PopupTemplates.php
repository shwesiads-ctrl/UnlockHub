<?php

namespace App\Support;

/**
 * Starting points for the popup editor. Admin picks one, then edits the text.
 * Keys/structure follow Popup::sanitize().
 */
class PopupTemplates
{
    public static function all(): array
    {
        $t = [
            'welcome' => ['Welcome (home page)', [
                'theme' => 'blue', 'align' => 'left',
                'title' => 'WELCOME {name}!', 'subtitle' => 'Register & **Knock Us** to Activate Your Account',
                'blocks' => [
                    ['type' => 'box', 'tone' => 'success', 'title' => 'Hacker Protection', 'text' => 'Please enable **Google 2FA Authentication** for account safety.'],
                    ['type' => 'box', 'tone' => 'danger', 'title' => 'IMPORTANT RULES', 'items' => [
                        '**Double Check:** IMEI & Model (Strictly No Refund).',
                        '**Working Days:** Monday to Friday Only.',
                        '**No Cancel:** Orders "In Process" cannot be rejected.',
                        '**Global Issues:** Please wait patiently during delays.',
                    ]],
                    ['type' => 'buttons', 'items' => [
                        ['label' => 'WhatsApp', 'url' => 'https://wa.me/959000000000', 'color' => 'whatsapp'],
                        ['label' => 'Telegram', 'url' => 'https://t.me/yourchannel', 'color' => 'telegram'],
                    ]],
                    ['type' => 'box', 'tone' => 'amber', 'title' => 'KBZPay / WavePay Top-up', 'text' => 'Upload your receipt in Add Funds — approved within minutes.', 'link_label' => '▶ Watch Video Guide', 'link_url' => 'https://youtube.com/'],
                ],
            ]],

            'imei' => ['IMEI service (details + instructions)', [
                'theme' => 'orange', 'align' => 'center',
                'title' => 'Account Remove Service • Worldwide • Only Clean',
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Service Details'],
                    ['type' => 'list', 'style' => 'bullet', 'items' => ['Supports **All Devices (Worldwide)** except China region models.', 'Instant removal – 100% safe method.', 'Supported: **Clean IMEIs only**.']],
                    ['type' => 'box', 'tone' => 'warning', 'text' => '⚠️ Wrong IMEI or Lock Code = **No Refund**'],
                    ['type' => 'heading', 'text' => 'Order Instructions'],
                    ['type' => 'list', 'style' => 'bullet', 'items' => ['Provide **Lock Code** or **IMEI Number** while placing order.', 'Double-check your details before submitting — incorrect info leads to cancellation without refund.', 'Delivery Time: Usually Instant or Same-Day Processing.']],
                    ['type' => 'heading', 'text' => 'Important Notes'],
                    ['type' => 'list', 'style' => 'cross', 'items' => ['Wrong IMEI, Junk Orders, or Fake Device Data will not be refunded.']],
                    ['type' => 'list', 'style' => 'check', 'items' => ['Only **Clean Devices** are supported for successful removal.']],
                    ['type' => 'buttons', 'items' => [['label' => 'Contact Admin Help & Support', 'url' => '/contact', 'color' => 'dark']]],
                ],
            ]],

            'tool' => ['Tool / software (changelog + how to use)', [
                'theme' => 'navy', 'align' => 'center', 'badge' => 'NEW UPDATE',
                'title' => 'Tool Name v1.1 (Mac)', 'subtitle' => 'Short one-line description of the tool',
                'blocks' => [
                    ['type' => 'box', 'tone' => 'plain', 'title' => 'CHANGELOG V1.1 & CORE FEATURES:', 'items' => ['**New Function:** describe it here.', '**New Function:** another one.', 'Supported versions: x.x – y.y']],
                    ['type' => 'box', 'tone' => 'plain', 'half' => true, 'title' => 'Supported Models:', 'items' => ['Model A', 'Model B', 'Model C']],
                    ['type' => 'box', 'tone' => 'plain', 'half' => true, 'title' => 'Required Hardware:', 'items' => ['Adapter 1', 'Adapter 2']],
                    ['type' => 'box', 'tone' => 'plain', 'title' => 'HOW TO USE (STEP-BY-STEP):', 'items' => ['1. Connect the device.', '2. Run the Pre-Check.', '3. Click "Activate" to finish.']],
                    ['type' => 'box', 'tone' => 'danger', 'text' => '**TROUBLESHOOTING NOTE:** If it fails, update the tool to the latest version before retrying.'],
                    ['type' => 'box', 'tone' => 'plain', 'title' => 'SOFTWARE DOWNLOAD LINK:', 'link_label' => 'Download', 'link_url' => 'https://'],
                ],
            ]],

            'guide' => ['Guide with steps + supported / limitations', [
                'theme' => 'dark', 'align' => 'left', 'badge' => 'FIRST IN THE WORLD • 2026',
                'title' => 'SERVICE HEADLINE', 'subtitle' => 'Exclusive solution for supported devices',
                'blocks' => [
                    ['type' => 'box', 'tone' => 'plain', 'text' => '**System Support:** latest versions compatible'],
                    ['type' => 'box', 'tone' => 'success', 'half' => true, 'title' => 'FULLY FUNCTIONAL ✅', 'items' => ['Feature one', 'Feature two', 'Feature three']],
                    ['type' => 'box', 'tone' => 'amber', 'half' => true, 'title' => 'CURRENT STATUS & LIMITATIONS ⚠️', 'items' => ['Limitation one', 'Limitation two'], 'text' => '**Note:** anything customers should know.'],
                    ['type' => 'steps', 'title' => 'STEP-BY-STEP GUIDELINE', 'items' => [
                        ['title' => 'STEP 01. PREPARE', 'text' => 'What to do first.', 'link_label' => 'Download tool →', 'link_url' => 'https://'],
                        ['title' => 'STEP 02. SETUP', 'text' => 'Install and open the app package.', 'link_label' => '', 'link_url' => ''],
                        ['title' => 'STEP 03. REGISTER', 'text' => 'Register the device ID by placing an order here.', 'link_label' => '', 'link_url' => ''],
                        ['title' => 'STEP 04. RUN', 'text' => 'Follow the guided prompts to finish.', 'link_label' => '', 'link_url' => ''],
                    ]],
                ],
                'footer' => 'OFFICIAL AUTOMATED API PROCESSING HUB',
            ]],

            'region' => ['Region check + chips (country codes)', [
                'theme' => 'orange', 'align' => 'center', 'badge' => 'OFFICIAL EUROPE SERVER ✅',
                'title' => 'Account Removal', 'subtitle' => 'Premium Unlock Service',
                'blocks' => [
                    ['type' => 'box', 'tone' => 'info', 'title' => '⚠️ REGION VERIFICATION REQUIRED', 'text' => "Check the code displayed on your screen before ordering.\n❌ **IF YOU SEE (+8…) DO NOT ORDER HERE (STRICT NO REFUND)**"],
                    ['type' => 'chips', 'title' => '🌍 SUPPORTED REGIONS & CODES', 'items' => ['Austria (+43)', 'Belgium (+32)', 'France (+33)', 'Germany (+49)', 'Italy (+39)', 'Spain (+34)']],
                    ['type' => 'box', 'tone' => 'danger', 'title' => '🚫 STRICT TERMS OF SERVICE', 'items' => ['Submit ONLY CLEAN UNLOCK CODE (No phone numbers).', 'LOST / BLACKLISTED devices are strictly not supported.', 'Wrong Region / Global Device = NO REFUND.']],
                    ['type' => 'buttons', 'items' => [['label' => 'SUBMIT LOCK CODE NOW', 'url' => '#order', 'color' => 'dark']]],
                ],
            ]],

            'clean' => ['Simple: accepted / not supported', [
                'theme' => 'none',
                'blocks' => [
                    ['type' => 'box', 'tone' => 'info', 'title' => 'SUPPORTED MODELS', 'text' => 'All iPhone Models **Up to iPhone 14 Series**'],
                    ['type' => 'box', 'tone' => 'success', 'half' => true, 'title' => 'ACCEPTED', 'text' => '✅ **Clean Only**'],
                    ['type' => 'box', 'tone' => 'danger', 'half' => true, 'title' => 'NOT SUPPORTED', 'text' => '⛔ **Blacklisted / Unpaid**'],
                    ['type' => 'box', 'tone' => 'plain', 'text' => "⏳ **Processing Time:** 1 – 10 Working Days.\n⚠️ **Strict Policy:** Wrong carrier or Blacklist — **No Refund**."],
                    ['type' => 'buttons', 'items' => [['label' => 'SUPPORT HELP ✅', 'url' => '/contact', 'color' => 'green']]],
                ],
                'footer' => '100% PERMANENT FACTORY UNLOCK',
            ]],

            'fmi' => ['Clean device + numbered instructions', [
                'theme' => 'sky', 'align' => 'center',
                'title' => 'REMOVE SERVICE CLEAN DEVICE - PERMANENT FMI OFF',
                'blocks' => [
                    ['type' => 'box', 'tone' => 'success', 'title' => 'DEVICE CONDITION: CLEAN MODE ONLY', 'text' => 'Lost or Stolen devices are strictly NOT supported.'],
                    ['type' => 'steps', 'title' => 'REQUIRED INSTRUCTIONS', 'items' => [
                        ['title' => '', 'text' => '1. Fresh flash your device using the latest version.', 'link_label' => '', 'link_url' => ''],
                        ['title' => '', 'text' => '2. Enter the correct **Serial Number (SN)** in the order field.', 'link_label' => '', 'link_url' => ''],
                        ['title' => '', 'text' => '3. Wait for the "FMI OFF" status.', 'link_label' => '', 'link_url' => ''],
                        ['title' => '', 'text' => '4. Once confirmed, activate the device with your own ID.', 'link_label' => '', 'link_url' => ''],
                    ]],
                    ['type' => 'box', 'tone' => 'amber', 'title' => 'PROCESSING TIMEFRAME', 'text' => "**USUALLY A FEW HOURS**\nNote: Additional days may be added if the server is overloaded."],
                    ['type' => 'box', 'tone' => 'danger', 'text' => '**VERIFICATION: ALWAYS CHECK FMI STATUS BEFORE AND AFTER SERVICE.**'],
                    ['type' => 'buttons', 'items' => [['label' => 'CONTACT WHATSAPP', 'url' => 'https://wa.me/959000000000', 'color' => 'whatsapp']]],
                ],
            ]],

            'carrier' => ['Carrier unlock + eligible checker sample', [
                'theme' => 'navy', 'align' => 'center', 'badge' => 'SPECIAL UNLOCK SERVICE',
                'title' => 'Carrier A → Carrier B Unlock', 'subtitle' => 'Initial Policy: Carrier A | Current Policy: Carrier B',
                'blocks' => [
                    ['type' => 'box', 'tone' => 'amber', 'title' => '⚠️ STRICT ORDER ELIGIBILITY RULES (CHECK POLICY FIRST):', 'items' => ['**Requirement 1:** Device carrier must be Carrier B.', '**Requirement 2:** Initial policy must be Carrier A.', 'Submissions are accepted ONLY if Requirement 2 is met! Run a policy check before submitting.']],
                    ['type' => 'table', 'title' => 'ELIGIBLE CHECKER SAMPLE', 'rows' => [
                        ['key' => 'Model:', 'value' => 'iPhone XS Max', 'tone' => ''],
                        ['key' => 'IMEI:', 'value' => '35xxxxxxxxxxxxx', 'tone' => ''],
                        ['key' => 'Blacklist Status:', 'value' => 'Clean', 'tone' => 'green'],
                        ['key' => 'Contract Status:', 'value' => 'Fully Paid (Eligible)', 'tone' => 'green'],
                        ['key' => 'Carrier A → Carrier B:', 'value' => 'Yes', 'tone' => 'green'],
                    ]],
                    ['type' => 'box', 'tone' => 'danger', 'text' => '**REQUIRED INPUT:** submit your exact 15-digit IMEI number. Make sure the initial policy is correct to prevent rejections.'],
                    ['type' => 'buttons', 'items' => [['label' => 'FAST AND AUTOMATED API SERVICE', 'url' => '', 'color' => 'dark']]],
                ],
            ]],

            'code' => ['Code service (procedure + refund policy)', [
                'theme' => 'amber', 'align' => 'center',
                'title' => 'NETWORK & REGION UNLOCK SERVICE ✅',
                'blocks' => [
                    ['type' => 'heading', 'text' => 'PROCEDURE'],
                    ['type' => 'box', 'tone' => 'amber', 'items' => ['1. Connect to **Stable Wi-Fi**.', '2. Dial the service code shown in the guide.', '3. Paste your **172-Digit Code** in the order form.', '4. Check version and status after unlocking.']],
                    ['type' => 'heading', 'text' => 'CRITICAL POLICY & REFUND'],
                    ['type' => 'box', 'tone' => 'danger', 'items' => ['⚠️ **Unlock Immediately:** test the unlock right after success.', '❌ **Reporting Errors:** report to Support **within 24 hours** of completion.', '⚠️ **Strict No-Refund Rule:** issues reported after 24 hours will NOT be accepted.']],
                    ['type' => 'box', 'tone' => 'plain', 'text' => '⚠️ **Requirement:** Must provide Video Proof + Order ID + Screenshot of error.'],
                    ['type' => 'buttons', 'items' => [['label' => 'HELP ✅', 'url' => '/contact', 'color' => 'green']]],
                ],
            ]],

            'blank' => ['Blank', ['theme' => 'blue', 'title' => '', 'blocks' => []]],
        ];

        return collect($t)->map(fn ($v) => ['label' => $v[0], 'popup' => Popup::sanitize($v[1] + ['enabled' => true])])->all();
    }
}
