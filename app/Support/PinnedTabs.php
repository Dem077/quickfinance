<?php

namespace App\Support;

class PinnedTabs
{
    public const PURCHASE_REQUESTS = 'purchase_requests';

    public const PURCHASE_ORDERS = 'purchase_orders';

    public const PETTY_CASH = 'petty_cash';

    public const ASSET_MANAGEMENT = 'asset_management';

    public const BUDGET_TRANSACTION_HISTORIES = 'budget_transaction_histories';

    public const BUDGET_ACCOUNTS = 'budget_accounts';

    public const ACTIVITY = 'activity';

    public const EMAILS = 'emails';

    /**
     * @return list<string>
     */
    public static function pages(): array
    {
        return [
            self::PURCHASE_REQUESTS,
            self::PURCHASE_ORDERS,
            self::PETTY_CASH,
            self::ASSET_MANAGEMENT,
            self::BUDGET_TRANSACTION_HISTORIES,
            self::BUDGET_ACCOUNTS,
            self::ACTIVITY,
            self::EMAILS,
        ];
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    public static function resolve(?string $requested, ?string $preferred, array $allowedKeys, string $fallback = 'all'): string
    {
        if ($requested !== null && $requested !== '' && in_array($requested, $allowedKeys, true)) {
            return $requested;
        }

        if ($preferred !== null && $preferred !== '' && in_array($preferred, $allowedKeys, true)) {
            return $preferred;
        }

        if ($allowedKeys !== [] && in_array($fallback, $allowedKeys, true)) {
            return $fallback;
        }

        return $allowedKeys[0] ?? $fallback;
    }
}
