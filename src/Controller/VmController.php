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

    // ============================================================
    // Simplified Endpoints (without node in path)
    // ============================================================

    /**
     * Start a VM (simplified - auto-detects node)
     * POST /api/v1/vms/{vmid}/start
     */
    #[Route('/vms/{vmid}/start', name: 'api_vms_start_simple', methods: ['POST'])]
    public function startSimple(int $vmid): JsonResponse
    {
        try {
            $node = $this->proxmoxClient->findVMNode($vmid);
            $result = $this->proxmoxClient->startVM($node, $vmid);

            return $this->json([
                'success' => true,
                'message' => 'VM start command sent successfully',
                'node' => $node,
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
     * Stop a VM (simplified - auto-detects node, supports mode)
     * POST /api/v1/vms/{vmid}/stop
     * Body: { "mode": "acpi" } or { "mode": "hard" }
     */
    #[Route('/vms/{vmid}/stop', name: 'api_vms_stop_simple', methods: ['POST'])]
    public function stopSimple(int $vmid, Request $request): JsonResponse
    {
        try {
            $node = $this->proxmoxClient->findVMNode($vmid);
            
            // Get mode from request body
            $data = json_decode($request->getContent(), true);
            $mode = $data['mode'] ?? 'acpi'; // Default to ACPI (graceful)
            
            // Validate mode
            if (!in_array($mode, ['acpi', 'hard'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid mode. Must be "acpi" or "hard"',
                ], 400);
            }

            $result = $this->proxmoxClient->stopVMWithMode($node, $vmid, $mode);

            return $this->json([
                'success' => true,
                'message' => "VM stop command sent successfully (mode: {$mode})",
                'node' => $node,
                'mode' => $mode,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // Snapshot Management
    // ============================================================

    /**
     * Get all snapshots for a VM
     * GET /api/v1/vms/{vmid}/snapshots
     */
    #[Route('/vms/{vmid}/snapshots', name: 'api_vms_snapshots_list', methods: ['GET'])]
    public function listSnapshots(int $vmid): JsonResponse
    {
        try {
            $node = $this->proxmoxClient->findVMNode($vmid);
            $snapshots = $this->proxmoxClient->getSnapshots($node, $vmid);

            return $this->json([
                'success' => true,
                'data' => $snapshots,
                'count' => count($snapshots),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new snapshot
     * POST /api/v1/vms/{vmid}/snapshot
     * Body: { "name": "snapshot-name", "description": "optional description" }
     */
    #[Route('/vms/{vmid}/snapshot', name: 'api_vms_snapshot_create', methods: ['POST'])]
    public function createSnapshot(int $vmid, Request $request): JsonResponse
    {
        try {
            $node = $this->proxmoxClient->findVMNode($vmid);
            
            // Get snapshot name from request body
            $data = json_decode($request->getContent(), true);
            $snapname = $data['name'] ?? null;
            $description = $data['description'] ?? null;

            if (!$snapname) {
                return $this->json([
                    'success' => false,
                    'error' => 'Snapshot name is required in request body',
                ], 400);
            }

            $result = $this->proxmoxClient->createSnapshot($node, $vmid, $snapname, $description);

            return $this->json([
                'success' => true,
                'message' => 'Snapshot creation initiated',
                'snapshot' => $snapname,
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
     * Rollback to a snapshot
     * POST /api/v1/vms/{vmid}/snapshot/{snapname}/rollback
     */
    #[Route('/vms/{vmid}/snapshot/{snapname}/rollback', name: 'api_vms_snapshot_rollback', methods: ['POST'])]
    public function rollbackSnapshot(int $vmid, string $snapname): JsonResponse
    {
        try {
            $node = $this->proxmoxClient->findVMNode($vmid);
            $result = $this->proxmoxClient->rollbackSnapshot($node, $vmid, $snapname);

            return $this->json([
                'success' => true,
                'message' => "Rollback to snapshot '{$snapname}' initiated",
                'snapshot' => $snapname,
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
