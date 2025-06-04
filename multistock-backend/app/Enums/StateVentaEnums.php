<?php

namespace App\Enums;

enum StateVentaEnums: string
{
    case FINISHED = 'Finalizado';
    case PENDING = 'Pendiente';
    case CANCELLED = 'Cancelado';
    case ISSUED = 'Emitido';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
