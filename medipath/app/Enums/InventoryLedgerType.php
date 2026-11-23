<?php

namespace App\Enums;

enum InventoryLedgerType: string
{
    case Restock = 'restock';
    case Dispense = 'dispense';
    case Adjustment = 'adjustment';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Restock => 'Restock',
            self::Dispense => 'Dispense',
            self::Adjustment => 'Adjustment',
            self::Return => 'Return',
        };
    }
}
