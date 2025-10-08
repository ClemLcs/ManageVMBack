<?php

namespace App\Controller;

use App\Service\ProxmoxClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1')]
class EventsController extends AbstractController
{
    public function __construct(
        private ProxmoxClient $proxmoxClient
    ) {
    }

    /**
     * Stream Proxmox events/tasks using Server-Sent Events (SSE)
     * GET /api/v1/events/stream
     */
    #[Route('/events/stream', name: 'api_events_stream', methods: ['GET'])]
    public function eventStream(): StreamedResponse
    {
        $response = new StreamedResponse();
        
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Disable nginx buffering

        $response->setCallback(function () {
            $lastTaskId = null;
            
            // Send initial connection message
            echo "event: connected\n";
            echo "data: " . json_encode(['message' => 'Connected to event stream']) . "\n\n";
            ob_flush();
            flush();

            // Keep streaming events
            while (true) {
                try {
                    // Get latest tasks from Proxmox
                    $tasks = $this->proxmoxClient->getClusterTasks(50);
                    
                    // Filter new tasks (tasks we haven't seen before)
                    $newTasks = [];
                    foreach ($tasks as $task) {
                        $taskId = $task['upid'] ?? null;
                        if (!$taskId) {
                            continue;
                        }
                        
                        // If this is the first iteration, set the latest task ID
                        if ($lastTaskId === null) {
                            $lastTaskId = $taskId;
                            break; // Don't send all existing tasks on first load
                        }
                        
                        // Stop if we've reached tasks we've already seen
                        if ($taskId === $lastTaskId) {
                            break;
                        }
                        
                        $newTasks[] = $task;
                    }
                    
                    // Update last task ID
                    if (!empty($tasks)) {
                        $lastTaskId = $tasks[0]['upid'] ?? $lastTaskId;
                    }
                    
                    // Send new tasks as events
                    foreach (array_reverse($newTasks) as $task) {
                        $eventType = $this->getEventType($task);
                        
                        echo "event: {$eventType}\n";
                        echo "data: " . json_encode($task) . "\n\n";
                        ob_flush();
                        flush();
                    }
                    
                    // Send heartbeat every 15 seconds to keep connection alive
                    echo "event: heartbeat\n";
                    echo "data: " . json_encode(['timestamp' => time()]) . "\n\n";
                    ob_flush();
                    flush();
                    
                } catch (\Exception $e) {
                    // Send error event
                    echo "event: error\n";
                    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
                    ob_flush();
                    flush();
                }
                
                // Check if client is still connected
                if (connection_aborted()) {
                    break;
                }
                
                // Wait 2 seconds before next poll
                sleep(2);
            }
        });

        return $response;
    }

    /**
     * Determine event type based on task type
     */
    private function getEventType(array $task): string
    {
        $type = $task['type'] ?? 'unknown';
        
        // Map Proxmox task types to event types
        return match ($type) {
            'qmstart' => 'vm_start',
            'qmstop' => 'vm_stop',
            'qmshutdown' => 'vm_shutdown',
            'qmreboot' => 'vm_reboot',
            'qmsuspend' => 'vm_suspend',
            'qmresume' => 'vm_resume',
            'qmsnapshot' => 'vm_snapshot',
            'qmrollback' => 'vm_rollback',
            'qmcreate' => 'vm_create',
            'qmdestroy' => 'vm_destroy',
            'qmclone' => 'vm_clone',
            'qmmigrate' => 'vm_migrate',
            default => 'task',
        };
    }
}
