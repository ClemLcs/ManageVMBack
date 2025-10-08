<?php

namespace App\Controller;

use App\Service\ProxmoxClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private ProxmoxClient $proxmoxClient
    ) {
    }

    /**
     * Basic liveness probe
     * Returns 200 if the application is running
     * GET /health
     */
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => time(),
            'service' => 'ManageVMBack',
        ]);
    }

    /**
     * Readiness probe
     * Returns 200 if the application is ready to serve traffic
     * Checks if Proxmox API is accessible
     * GET /ready
     */
    #[Route('/ready', name: 'readiness_check', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        try {
            // Check if Proxmox is accessible
            $isHealthy = $this->proxmoxClient->checkHealth();
            
            if (!$isHealthy) {
                return $this->json([
                    'status' => 'not_ready',
                    'reason' => 'Cannot connect to Proxmox API',
                    'timestamp' => time(),
                ], 503);
            }

            // Get Proxmox version to confirm API is working
            $version = $this->proxmoxClient->getVersion();

            return $this->json([
                'status' => 'ready',
                'timestamp' => time(),
                'proxmox' => [
                    'connected' => true,
                    'version' => $version['version'] ?? 'unknown',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'not_ready',
                'reason' => 'Proxmox API error: ' . $e->getMessage(),
                'timestamp' => time(),
            ], 503);
        }
    }
}
