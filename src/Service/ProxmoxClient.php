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
}
