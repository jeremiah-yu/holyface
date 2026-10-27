<?php
declare(strict_types=1);

namespace HolyFace\Api\Controllers;

use HolyFace\Api\Http\Request;
use HolyFace\Api\Http\Response;
use HolyFace\Api\Services\AuditService;
use HolyFace\Api\Services\ConsentService;

final class LegalController
{
    public function __construct(
        private ConsentService $consent,
        private AuditService $audit,
    ) {}

    public function policies(): void
    {
        $catalog = $this->consent->catalog();
        Response::json([
            'success' => true,
            'version_bundle' => $catalog['version'],
            'disclaimer' => $catalog['disclaimer'],
            'documents' => $this->consent->currentDocuments(),
        ]);
    }

    public function status(Request $request, array $auth): void
    {
        Response::json(['success' => true] + $this->consent->status((int) $auth['user']['id'], (string) $auth['role']));
    }

    public function accept(Request $request, array $auth): void
    {
        $body = $request->body ?? [];
        if (empty($body['scrolled']) || empty($body['accepted'])) {
            Response::error('Please scroll through the policies and confirm acceptance.', 422);
            return;
        }
        try {
            $result = $this->consent->accept(
                (int) $auth['user']['id'],
                (string) $auth['role'],
                $body,
                $request->ip,
                $request->userAgent
            );
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }
        $this->audit->log((int) $auth['user']['id'], 'legal.accept', 'consent_logs', (string) $auth['user']['id'], [
            'recorded' => $result['recorded'],
        ], $request->ip);
        Response::json(['success' => true] + $result);
    }

    public function publish(Request $request, array $auth): void
    {
        if ($auth['role'] !== 'admin') {
            Response::error('Registrar / Admin only.', 403);
            return;
        }
        try {
            $docs = $this->consent->publish((int) $auth['user']['id'], $request->body ?? []);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
            return;
        }
        $this->audit->log((int) $auth['user']['id'], 'legal.publish', 'policy_documents', (string) ($request->body['document_code'] ?? ''), [
            'version' => $request->body['version'] ?? null,
        ], $request->ip);
        Response::json(['success' => true, 'documents' => $docs]);
    }
}
