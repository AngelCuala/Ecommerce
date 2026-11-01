<?php

namespace App\Services\IdCheck;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Asks Claude (vision) whether an uploaded image or PDF is a government-issued
 * photo ID. Never blocks sign-up because of an outage: if the check can't run,
 * the upload is accepted and marked "unchecked" for the admin to review.
 */
class IdDocumentChecker
{
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const PROMPT = <<<'TXT'
You check identity documents for ALVY, a Philippine online marketplace. A user uploaded the attached file as their valid government-issued ID during sign-up.

Decide whether it is a photo of (or scan of) a real government-issued identification card or document. Accept, for example: PhilSys National ID (PhilID / ePhilID), driver's license, passport, UMID, SSS or GSIS ID, PRC ID, postal ID, voter's ID, PhilHealth ID, TIN ID, senior citizen or PWD ID, NBI clearance, barangay ID, school or company ID with a photo, or a foreign government ID. Front or back side both count. A photo taken at an angle, with glare, or with fingers in frame still counts if the document is clearly an ID.

Reject anything else: selfies or portraits without an ID, screenshots, memes, scenery, products, receipts, blank or nearly blank images, drawings, or documents that are not IDs (bills, certificates, letters). Also reject if the image is so blurry, dark or cropped that you can't tell what it is.

Judge only what the document is. Do not transcribe or repeat any personal details (names, numbers, addresses, birthdays) in your answer.
TXT;

    public function check(UploadedFile $file): IdCheckResult
    {
        $config = config('services.id_check');

        if (! ($config['enabled'] ?? true)) {
            return IdCheckResult::unchecked('ID check is turned off.');
        }
        if (empty($config['api_key'])) {
            return IdCheckResult::unchecked('ID check is not configured (no API key).');
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, [...self::IMAGE_TYPES, 'application/pdf'], true)) {
            return new IdCheckResult(IdCheckResult::REJECTED, null, 'Unsupported file type.');
        }

        $data = base64_encode((string) file_get_contents($file->getRealPath()));
        $fileBlock = $mime === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mime, 'data' => $data]];

        try {
            $client = new Client(apiKey: $config['api_key']);

            $message = $client->messages->create(
                model: $config['model'],
                maxTokens: 4096,
                messages: [[
                    'role' => 'user',
                    'content' => [$fileBlock, ['type' => 'text', 'text' => self::PROMPT]],
                ]],
                // A yes/no judgement on one image: low effort is plenty.
                outputConfig: [
                    'effort' => 'low',
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'is_government_id' => ['type' => 'boolean'],
                                'document_type'    => ['type' => 'string', 'description' => 'e.g. "PhilSys National ID", "Driver\'s license", or what the image shows if not an ID'],
                                'reason'           => ['type' => 'string', 'description' => 'One short sentence, no personal details'],
                            ],
                            'required' => ['is_government_id', 'document_type', 'reason'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                requestOptions: ['timeout' => (float) ($config['timeout'] ?? 30), 'maxRetries' => 1],
            );
        } catch (AnthropicException $e) {
            Log::warning('ID check could not run: ' . $e->getMessage());
            return IdCheckResult::unchecked('ID check service unavailable.');
        } catch (\Throwable $e) {
            Log::error('ID check failed unexpectedly: ' . $e->getMessage());
            return IdCheckResult::unchecked('ID check failed.');
        }

        // A safety decline isn't a verdict about the document.
        if ($message->stopReason === 'refusal') {
            return IdCheckResult::unchecked('ID check declined to evaluate this file.');
        }

        $verdict = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $verdict = json_decode($block->text, true);
                break;
            }
        }
        if (! is_array($verdict) || ! array_key_exists('is_government_id', $verdict)) {
            return IdCheckResult::unchecked('ID check returned an unreadable answer.');
        }

        return new IdCheckResult(
            $verdict['is_government_id'] ? IdCheckResult::PASSED : IdCheckResult::REJECTED,
            $verdict['document_type'] ?? null,
            $verdict['reason'] ?? null,
        );
    }
}
