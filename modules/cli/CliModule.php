<?php
namespace modules\cli;

use Craft;
use yii\base\Module;
use yii\base\Event;
use craft\services\Dashboard;
use craft\events\RegisterComponentTypesEvent;
use craft\commerce\elements\Order;
use craft\commerce\services\Transactions;
use craft\commerce\events\TransactionEvent;
use craft\events\ModelEvent;
use modules\cli\widgets\RevenueWidget;
use modules\cli\services\RevenueService;

class CliModule extends Module
{
    public static ?CliModule $instance = null;

    public function init(): void
    {
        parent::init();
        self::$instance = $this;

        // Set controller namespace for console requests
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'modules\\cli\\console\\controllers';
        }

        // Register services
        $this->setComponents([
            'revenue' => RevenueService::class,
        ]);

        // Register custom widget
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = RevenueWidget::class;
            }
        );

        // Register order events (only for web requests to avoid CLI overhead)
        if (!Craft::$app->request->isConsoleRequest) {
            $this->_registerOrderEvents();
        }
    }

    /**
     * Get module instance
     */
    public static function getInstance(): ?CliModule
    {
        return self::$instance;
    }

    /**
     * Register order-related events
     */
    private function _registerOrderEvents(): void
    {
        // When order is completed
        Event::on(
            Order::class,
            Order::EVENT_AFTER_COMPLETE_ORDER,
            function (Event $event) {
                /** @var Order $order */
                $order = $event->sender;
                $this->revenue->addOrder(
                    (float) $order->total,
                    (float) $order->totalPaid,
                    $order->storeId,
                    $order->currency
                );
                Craft::info("Revenue updated: Order #{$order->id} completed", __METHOD__);
            }
        );

        // When transaction is successful (payment or refund)
        Event::on(
            Transactions::class,
            Transactions::EVENT_AFTER_SAVE_TRANSACTION,
            function (TransactionEvent $event) {
                $transaction = $event->transaction;

                if ($transaction->status !== 'success') {
                    return;
                }

                $order = $transaction->getOrder();
                if (!$order) {
                    return;
                }

                if ($transaction->type === 'refund') {
                    $this->revenue->addRefund(
                        (float) $transaction->amount,
                        $order->storeId
                    );
                    Craft::info("Revenue updated: Refund of {$transaction->amount} for Order #{$order->id}", __METHOD__);
                }
            }
        );

        // When order is deleted (soft delete)
        Event::on(
            Order::class,
            Order::EVENT_BEFORE_DELETE,
            function (ModelEvent $event) {
                /** @var Order $order */
                $order = $event->sender;

                if ($order->isCompleted) {
                    $this->revenue->removeOrder(
                        (float) $order->total,
                        (float) $order->totalPaid,
                        $order->storeId
                    );
                    Craft::info("Revenue updated: Order #{$order->id} deleted", __METHOD__);
                }
            }
        );

        // When order is RESTORED from trash
        Event::on(
            Order::class,
            Order::EVENT_AFTER_RESTORE,
            function (Event $event) {
                /** @var Order $order */
                $order = $event->sender;

                if ($order->isCompleted) {
                    $this->revenue->addOrder(
                        (float) $order->total,
                        (float) $order->totalPaid,
                        $order->storeId,
                        $order->currency
                    );
                    Craft::info("Revenue updated: Order #{$order->id} restored from trash", __METHOD__);
                }
            }
        );
    }
}