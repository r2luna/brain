# Broadcasting

Brain supports real-time broadcasting of process and task execution events, allowing you to monitor workflow progress live through WebSocket connections or other Laravel broadcast drivers.

## Quick Start

Enable broadcasting in your environment:

```bash
BRAIN_BROADCAST_ENABLED=true
BRAIN_BROADCAST_PROCESSES=true
BRAIN_BROADCAST_TASKS=true
```

Then listen for events in your frontend:

```javascript
Echo.channel('brain')
    .listen('.brain.process.started', (e) => {
        console.log('Process started:', e.message);
    })
    .listen('.brain.process.finished', (e) => {
        console.log('Process completed:', e.message);
    });
```

## Configuration

### Environment Variables

```bash
# Enable/disable broadcasting entirely
BRAIN_BROADCAST_ENABLED=true

# Control what gets broadcasted
BRAIN_BROADCAST_PROCESSES=true
BRAIN_BROADCAST_TASKS=true
```

### Config File

In `config/brain.php`:

```php
'broadcast' => [
    'enabled' => env('BRAIN_BROADCAST_ENABLED', false),
    'processes' => env('BRAIN_BROADCAST_PROCESSES', true),
    'tasks' => env('BRAIN_BROADCAST_TASKS', true),
],
```

## Broadcast Events

### Process Events

| Event | When | Channel |
|-------|------|---------|
| `ProcessStarted` | Process begins execution | `brain.process.started` |
| `ProcessFinished` | Process completes execution | `brain.process.finished` |

### Task Events

| Event | When | Channel |
|-------|------|---------|
| `TaskStarted` | Task begins execution | `brain.task.started` |
| `TaskFinished` | Task completes execution | `brain.task.finished` |

## Broadcast Channels

Events are automatically broadcasted to multiple channels:

- **Global**: `brain` - All Brain events
- **Type-specific**: `brain.process` or `brain.task` - All events of that type
- **Instance-specific**: `brain.process.{uuid}` or `brain.task.{taskId}` - Events for a specific execution

## Event Data Structure

Each broadcast event contains:

```php
[
    'id' => 'unique-identifier',           // UUID for processes, generated ID for tasks
    'name' => 'App\\Brain\\ProcessName',   // Class name
    'type' => 'process',                   // 'process' or 'task'
    'event' => 'started',                  // 'started' or 'finished'
    'message' => [                         // Your custom data
        'message' => 'Process started',
        'custom_field' => 'custom_value',
    ],
    'meta' => [                           // System metadata
        'timestamp' => 1234567890.123,
        'payload_keys' => ['userId', 'email'],
        // Additional context for tasks:
        'process' => 'App\\Brain\\ParentProcess',
        'runProcessId' => 'parent-uuid',
    ],
    'timestamp' => '2024-01-15T10:30:00Z' // ISO-8601 timestamp
]
```

## Custom Broadcast Messages

### Process Broadcasting

Override these methods in your process to customize broadcast messages:

```php
<?php

use Brain\Process;

class OrderProcessing extends Process
{
    protected array $tasks = [
        ValidateOrder::class,
        ChargePayment::class,
        SendConfirmation::class,
    ];

    protected function startedBroadcastMessage(): array
    {
        return [
            'message' => 'Order processing started',
            'order_id' => $this->orderId,
            'customer_name' => $this->customerName,
            'total_items' => count($this->items),
        ];
    }

    protected function finishedBroadcastMessage(): array
    {
        return [
            'message' => 'Order processed successfully',
            'order_id' => $this->orderId,
            'total_amount' => $this->totalAmount,
            'confirmation_number' => $this->confirmationNumber,
        ];
    }
}
```

### Task Broadcasting

Override these methods in your task to customize broadcast messages:

```php
<?php

use Brain\Task;

class ChargePayment extends Task
{
    protected function startedBroadcastMessage(): array
    {
        return [
            'message' => 'Processing payment',
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
            'currency' => $this->currency,
        ];
    }

    protected function finishedBroadcastMessage(): array
    {
        return [
            'message' => 'Payment processed successfully',
            'transaction_id' => $this->transactionId,
            'amount_charged' => $this->amount,
            'status' => 'completed',
        ];
    }

    public function handle(): static
    {
        // Process payment logic...
        $this->transactionId = 'txn_123456';
        
        return $this;
    }
}
```

## Frontend Integration

### Laravel Echo Setup

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
});
```

### Listening to Events

#### Global Monitoring

Listen to all Brain events:

```javascript
Echo.channel('brain')
    .listen('.brain.process.started', (e) => {
        console.log('Process started:', e);
        showNotification(`${e.name} started`);
    })
    .listen('.brain.process.finished', (e) => {
        console.log('Process finished:', e);
        showNotification(`${e.name} completed`);
    })
    .listen('.brain.task.started', (e) => {
        console.log('Task started:', e);
        updateProgressBar(e);
    })
    .listen('.brain.task.finished', (e) => {
        console.log('Task finished:', e);
        updateProgressBar(e);
    });
```

#### Specific Process Monitoring

Track a specific process execution:

```javascript
// Assuming you have the process UUID from when you dispatched it
const processId = 'uuid-from-dispatch';

Echo.channel(`brain.process.${processId}`)
    .listen('.brain.process.started', (e) => {
        showProgress('Starting...');
    })
    .listen('.brain.process.finished', (e) => {
        showProgress('Completed!');
        hideProgressModal();
    });
```

#### Type-Specific Monitoring

Monitor all processes or all tasks:

```javascript
// All process events
Echo.channel('brain.process')
    .listen('.brain.process.started', handleProcessStart)
    .listen('.brain.process.finished', handleProcessFinish);

// All task events  
Echo.channel('brain.task')
    .listen('.brain.task.started', handleTaskStart)
    .listen('.brain.task.finished', handleTaskFinish);
```

## Real-World Examples

### Order Processing Dashboard

```javascript
class OrderDashboard {
    constructor() {
        this.activeOrders = new Map();
        this.setupListeners();
    }

    setupListeners() {
        Echo.channel('brain.process')
            .listen('.brain.process.started', (e) => {
                if (e.name.includes('OrderProcessing')) {
                    this.addOrder(e);
                }
            })
            .listen('.brain.process.finished', (e) => {
                if (e.name.includes('OrderProcessing')) {
                    this.completeOrder(e);
                }
            });

        Echo.channel('brain.task')
            .listen('.brain.task.finished', (e) => {
                this.updateTaskProgress(e);
            });
    }

    addOrder(event) {
        const order = {
            id: event.id,
            orderId: event.message.order_id,
            customer: event.message.customer_name,
            status: 'processing',
            startTime: event.timestamp,
            completedTasks: 0,
            totalTasks: event.message.tasks_count || 3
        };
        
        this.activeOrders.set(event.id, order);
        this.renderOrder(order);
    }

    updateTaskProgress(event) {
        if (event.meta.runProcessId && this.activeOrders.has(event.meta.runProcessId)) {
            const order = this.activeOrders.get(event.meta.runProcessId);
            order.completedTasks++;
            this.updateOrderDisplay(order);
        }
    }

    completeOrder(event) {
        const order = this.activeOrders.get(event.id);
        if (order) {
            order.status = 'completed';
            order.endTime = event.timestamp;
            order.confirmationNumber = event.message.confirmation_number;
            this.updateOrderDisplay(order);
        }
    }
}

new OrderDashboard();
```

### Progress Tracking Component (React)

```javascript
import { useEffect, useState } from 'react';

function ProcessProgress({ processId }) {
    const [status, setStatus] = useState('pending');
    const [message, setMessage] = useState('');
    const [tasks, setTasks] = useState([]);

    useEffect(() => {
        const channel = Echo.channel(`brain.process.${processId}`);

        channel.listen('.brain.process.started', (e) => {
            setStatus('running');
            setMessage(e.message.message);
        });

        channel.listen('.brain.process.finished', (e) => {
            setStatus('completed');
            setMessage(e.message.message);
        });

        // Listen to task events for this process
        const taskChannel = Echo.channel('brain.task');
        
        taskChannel.listen('.brain.task.finished', (e) => {
            if (e.meta.runProcessId === processId) {
                setTasks(prev => [...prev, {
                    name: e.name,
                    message: e.message.message,
                    timestamp: e.timestamp
                }]);
            }
        });

        return () => {
            channel.stopListening('.brain.process.started');
            channel.stopListening('.brain.process.finished');
            taskChannel.stopListening('.brain.task.finished');
        };
    }, [processId]);

    return (
        <div className="process-progress">
            <h3>Process Status: {status}</h3>
            <p>{message}</p>
            
            <h4>Completed Tasks:</h4>
            <ul>
                {tasks.map((task, index) => (
                    <li key={index}>
                        {task.name}: {task.message}
                        <small>({new Date(task.timestamp).toLocaleTimeString()})</small>
                    </li>
                ))}
            </ul>
        </div>
    );
}
```

## Performance Considerations

### Channel Management

- **Use specific channels** when possible to reduce unnecessary traffic
- **Unsubscribe** from channels when components unmount
- **Batch updates** if receiving many rapid events

### Filtering Events

```javascript
Echo.channel('brain')
    .listen('.brain.process.started', (e) => {
        // Only handle events for processes we care about
        if (e.name.includes('Order') || e.name.includes('Payment')) {
            handleImportantProcess(e);
        }
    });
```

### Error Handling

```javascript
Echo.channel('brain.process')
    .listen('.brain.process.started', (e) => {
        try {
            handleProcessStart(e);
        } catch (error) {
            console.error('Error handling process start:', error);
        }
    })
    .error((error) => {
        console.error('Channel error:', error);
    });
```

## Troubleshooting

### Events Not Broadcasting

1. **Check configuration**: Ensure `BRAIN_BROADCAST_ENABLED=true`
2. **Verify Laravel broadcasting setup**: Make sure your broadcast driver is configured
3. **Check permissions**: Ensure your WebSocket connection has proper authentication
4. **Test basic Laravel broadcasting**: Verify that standard Laravel events broadcast correctly

### Missing Events

1. **Check specific settings**: Verify `BRAIN_BROADCAST_PROCESSES` and `BRAIN_BROADCAST_TASKS` are enabled
2. **Verify channel subscriptions**: Make sure you're listening to the correct channels
3. **Check event names**: Event names are case-sensitive and prefixed with a dot (`.brain.process.started`)

### Performance Issues

1. **Use specific channels**: Don't listen to the global `brain` channel in production unless necessary
2. **Implement debouncing**: For rapid task updates, debounce UI updates
3. **Limit concurrent subscriptions**: Only subscribe to channels you're actively monitoring

## Security

### Channel Authorization

For private channels, implement authorization in your `BroadcastServiceProvider`:

```php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('brain.process.{id}', function ($user, $id) {
    // Authorize user access to this process
    return $user->canViewProcess($id);
});
```

### Data Filtering

Be mindful of sensitive data in broadcast messages. Avoid including:
- User passwords
- API keys
- Personal identification information
- Internal system details

Use the `#[Sensitive]` attribute on your processes and tasks to automatically redact sensitive payload data.