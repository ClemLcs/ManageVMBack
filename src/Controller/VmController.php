<?php

namespace App\Controller;

use App\Service\ProxmoxClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1')]
class VmController extends AbstractController
{
    public function __construct(
        private ProxmoxClient $proxmoxClient
    ) {
    }

    /**
     * Get list of VMs with filtering and pagination
     * GET /api/v1/vms?state=running&node=proxmox1&page=1&pageSize=50
     */
    #[Route('/vms', name: 'api_vms_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            // Get query parameters
            $state = $request->query->get('state'); // running, stopped, paused
            $node = $request->query->get('node');
            $page = max(1, (int) $request->query->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 50)));

            // Get VMs from Proxmox
            $vms = $this->proxmoxClient->getVMs($node, $state);

            // Calculate pagination
            $total = count($vms);
            $totalPages = ceil($total / $pageSize);
            $offset = ($page - 1) * $pageSize;
            
            // Slice the array for pagination
            $paginatedVms = array_slice($vms, $offset, $pageSize);

            // Format response
            return $this->json([
                'success' => true,
                'data' => $paginatedVms,
                'pagination' => [
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'total' => $total,
                    'totalPages' => $totalPages,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed information about a specific VM
     * GET /api/v1/vms/{node}/{vmid}
     */
    #[Route('/vms/{node}/{vmid}', name: 'api_vms_get', methods: ['GET'])]
    public function get(string $node, int $vmid): JsonResponse
    {
        try {
            $vm = $this->proxmoxClient->getVM($node, $vmid);

            return $this->json([
                'success' => true,
                'data' => $vm,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start a VM
     * POST /api/v1/vms/{node}/{vmid}/start
     */
    #[Route('/vms/{node}/{vmid}/start', name: 'api_vms_start', methods: ['POST'])]
    public function start(string $node, int $vmid): JsonResponse
    {
        try {
            $result = $this->proxmoxClient->startVM($node, $vmid);

            return $this->json([
                'success' => true,
                'message' => 'VM start command sent successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stop a VM (forced)
     * POST /api/v1/vms/{node}/{vmid}/stop
     */
    #[Route('/vms/{node}/{vmid}/stop', name: 'api_vms_stop', methods: ['POST'])]
    public function stop(string $node, int $vmid): JsonResponse
    {
        try {
            $result = $this->proxmoxClient->stopVM($node, $vmid);

            return $this->json([
                'success' => true,
                'message' => 'VM stop command sent successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Shutdown a VM (graceful)
     * POST /api/v1/vms/{node}/{vmid}/shutdown
     */
    #[Route('/vms/{node}/{vmid}/shutdown', name: 'api_vms_shutdown', methods: ['POST'])]
    public function shutdown(string $node, int $vmid): JsonResponse
    {
        try {
            $result = $this->proxmoxClient->shutdownVM($node, $vmid);

            return $this->json([
                'success' => true,
                'message' => 'VM shutdown command sent successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reboot a VM
     * POST /api/v1/vms/{node}/{vmid}/reboot
     */
    #[Route('/vms/{node}/{vmid}/reboot', name: 'api_vms_reboot', methods: ['POST'])]
    public function reboot(string $node, int $vmid): JsonResponse
    {
        try {
            $result = $this->proxmoxClient->rebootVM($node, $vmid);

            return $this->json([
                'success' => true,
                'message' => 'VM reboot command sent successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Suspend a VM
     * POST /api/v1/vms/{node}/{vmid}/suspend
     */
    #[Route('/vms/{node}/{vmid}/suspend', name: 'api_vms_suspend', methods: ['POST'])]
    public function suspend(string $node, int $vmid): JsonResponse
    {
        try {
            $result = $this->proxmoxClient->suspendVM($node, $vmid);

            return $this->json([
                'success' => true,
                'message' => 'VM suspend command sent successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resume a VM
     * POST /api/v1/vms/{node}/{vmid}/resume
     */
    #[Route('/vms/{node}/{vmid}/resume', name: 'api_vms_resume', methods: ['POST'])]
    public function resume(string $node, int $vmid): JsonResponse
    {
        try {
            $result = $this->proxmoxClient->resumeVM($node, $vmid);

            return $this->json([
                'success' => true,
                'message' => 'VM resume command sent successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
