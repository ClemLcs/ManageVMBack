<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ProxmoxClient
{
    private ?string $ticket = null;
    private ?string $csrfToken = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $proxmoxHost,
        private string $proxmoxUser,
        private string $proxmoxPassword,
        private bool $verifySsl = false
    ) {
    }

    /**
     * Authenticate with Proxmox API and get ticket
     */
    private function authenticate(): void
    {
        if ($this->ticket !== null) {
            return; // Already authenticated
        }

        $response = $this->httpClient->request('POST', $this->proxmoxHost . '/api2/json/access/ticket', [
            'verify_peer' => $this->verifySsl,
            'verify_host' => $this->verifySsl,
            'body' => [
                'username' => $this->proxmoxUser,
                'password' => $this->proxmoxPassword,
            ],
        ]);

        $data = $response->toArray();
        $this->ticket = $data['data']['ticket'];
        $this->csrfToken = $data['data']['CSRFPreventionToken'];
    }

    /**
     * Make a GET request to Proxmox API
     */
    public function get(string $endpoint): array
    {
        $this->authenticate();

        $response = $this->httpClient->request('GET', $this->proxmoxHost . '/api2/json' . $endpoint, [
            'verify_peer' => $this->verifySsl,
            'verify_host' => $this->verifySsl,
            'headers' => [
                'Cookie' => 'PVEAuthCookie=' . $this->ticket,
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Make a POST request to Proxmox API
     */
    public function post(string $endpoint, array $data = []): array
    {
        $this->authenticate();

        $response = $this->httpClient->request('POST', $this->proxmoxHost . '/api2/json' . $endpoint, [
            'verify_peer' => $this->verifySsl,
            'verify_host' => $this->verifySsl,
            'headers' => [
                'Cookie' => 'PVEAuthCookie=' . $this->ticket,
                'CSRFPreventionToken' => $this->csrfToken,
            ],
            'body' => $data,
        ]);

        return $response->toArray();
    }

    /**
     * Make a PUT request to Proxmox API
     */
    public function put(string $endpoint, array $data = []): array
    {
        $this->authenticate();

        $response = $this->httpClient->request('PUT', $this->proxmoxHost . '/api2/json' . $endpoint, [
            'verify_peer' => $this->verifySsl,
            'verify_host' => $this->verifySsl,
            'headers' => [
                'Cookie' => 'PVEAuthCookie=' . $this->ticket,
                'CSRFPreventionToken' => $this->csrfToken,
            ],
            'body' => $data,
        ]);

        return $response->toArray();
    }

    /**
     * Make a DELETE request to Proxmox API
     */
    public function delete(string $endpoint): array
    {
        $this->authenticate();

        $response = $this->httpClient->request('DELETE', $this->proxmoxHost . '/api2/json' . $endpoint, [
            'verify_peer' => $this->verifySsl,
            'verify_host' => $this->verifySsl,
            'headers' => [
                'Cookie' => 'PVEAuthCookie=' . $this->ticket,
                'CSRFPreventionToken' => $this->csrfToken,
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Get all VMs across all nodes or a specific node
     */
    public function getVMs(?string $node = null, ?string $state = null): array
    {
        $vms = [];

        if ($node) {
            // Get VMs from specific node
            $response = $this->get("/nodes/{$node}/qemu");
            $vms = $response['data'] ?? [];
        } else {
            // Get all nodes first
            $nodesResponse = $this->get('/nodes');
            $nodes = $nodesResponse['data'] ?? [];

            // Get VMs from all nodes
            foreach ($nodes as $nodeData) {
                $nodeName = $nodeData['node'];
                $response = $this->get("/nodes/{$nodeName}/qemu");
                $nodeVms = $response['data'] ?? [];
                
                // Add node name to each VM
                foreach ($nodeVms as &$vm) {
                    $vm['node'] = $nodeName;
                }
                
                $vms = array_merge($vms, $nodeVms);
            }
        }

        // Filter by state if provided
        if ($state) {
            $vms = array_filter($vms, function ($vm) use ($state) {
                return ($state === 'running' && $vm['status'] === 'running')
                    || ($state === 'stopped' && $vm['status'] === 'stopped')
                    || ($state === 'paused' && $vm['status'] === 'paused');
            });
        }

        return $vms;
    }

    /**
     * Get detailed information about a specific VM
     */
    public function getVM(string $node, int $vmid): array
    {
        $response = $this->get("/nodes/{$node}/qemu/{$vmid}/status/current");
        return $response['data'] ?? [];
    }

    /**
     * Start a VM
     */
    public function startVM(string $node, int $vmid): array
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/status/start");
    }

    /**
     * Stop a VM
     */
    public function stopVM(string $node, int $vmid): array
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/status/stop");
    }

    /**
     * Shutdown a VM (graceful)
     */
    public function shutdownVM(string $node, int $vmid): array
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/status/shutdown");
    }

    /**
     * Reboot a VM
     */
    public function rebootVM(string $node, int $vmid): array
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/status/reboot");
    }

    /**
     * Suspend a VM
     */
    public function suspendVM(string $node, int $vmid): array
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/status/suspend");
    }

    /**
     * Resume a VM
     */
    public function resumeVM(string $node, int $vmid): array
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/status/resume");
    }

    /**
     * Find which node a VM is on by its VMID
     * @throws \Exception if VM is not found
     */
    public function findVMNode(int $vmid): string
    {
        $vms = $this->getVMs();
        
        foreach ($vms as $vm) {
            if ($vm['vmid'] == $vmid) {
                return $vm['node'];
            }
        }
        
        throw new \Exception("VM with ID {$vmid} not found on any node");
    }

    /**
     * Stop a VM with mode (acpi or hard)
     * @param string $node Node name
     * @param int $vmid VM ID
     * @param string $mode 'acpi' for graceful shutdown or 'hard' for force stop
     */
    public function stopVMWithMode(string $node, int $vmid, string $mode = 'acpi'): array
    {
        if ($mode === 'acpi') {
            // Graceful ACPI shutdown
            return $this->post("/nodes/{$node}/qemu/{$vmid}/status/shutdown");
        } else {
            // Hard stop (immediate)
            return $this->post("/nodes/{$node}/qemu/{$vmid}/status/stop");
        }
    }

    /**
     * Get all snapshots for a VM
     */
    public function getSnapshots(string $node, int $vmid): array
    {
        $response = $this->get("/nodes/{$node}/qemu/{$vmid}/snapshot");
        return $response['data'] ?? [];
    }

    /**
     * Create a new snapshot
     * @param string $node Node name
     * @param int $vmid VM ID
     * @param string $snapname Snapshot name
     * @param string|null $description Optional description
     */
    public function createSnapshot(string $node, int $vmid, string $snapname, ?string $description = null): array
    {
        $data = ['snapname' => $snapname];
        if ($description) {
            $data['description'] = $description;
        }
        
        return $this->post("/nodes/{$node}/qemu/{$vmid}/snapshot", $data);
    }

    /**
     * Rollback to a snapshot
     */
    public function rollbackSnapshot(string $node, int $vmid, string $snapname): array
    {
        return $this->post("/nodes/{$node}/qemu/{$vmid}/snapshot/{$snapname}/rollback");
    }

    /**
     * Delete a snapshot
     */
    public function deleteSnapshot(string $node, int $vmid, string $snapname): array
    {
        return $this->delete("/nodes/{$node}/qemu/{$vmid}/snapshot/{$snapname}");
    }

    /**
     * Get cluster tasks (for event streaming)
     * @param int $limit Number of tasks to return
     */
    public function getClusterTasks(int $limit = 100): array
    {
        $response = $this->get("/cluster/tasks?limit={$limit}");
        return $response['data'] ?? [];
    }

    /**
     * Check if Proxmox API is reachable (for health checks)
     */
    public function checkHealth(): bool
    {
        try {
            $this->authenticate();
            $response = $this->get('/version');
            return isset($response['data']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Proxmox version information
     */
    public function getVersion(): array
    {
        $response = $this->get('/version');
        return $response['data'] ?? [];
    }
}
