<?php

namespace common\enums;

/**
 * Enum AdjustPriceType
 */
enum ChatRoomStatusType : string
{
    case STATUS_ACTIVE = 'active';

    case STATUS_INACTIVE = 'inactive';

    case STATUS_PENDING = 'pending';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
