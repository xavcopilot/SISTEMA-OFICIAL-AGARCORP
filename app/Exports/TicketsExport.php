<?php

namespace App\Exports;

use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Facades\DB;

class TicketsExport implements FromQuery, WithHeadings, WithEvents
{
    /**
     * Build a query for the export. We select only the columns that are
     * useful in the spreadsheet; feel free to add or remove fields later.
     */
    public function query()
    {
        // join with users to include creator name
        return Ticket::query()
            ->join('users', 'tickets.user_id', '=', 'users.id')
            ->select([
                'tickets.created_at',
                'tickets.nombre_solicitante',
                'tickets.departamento',
                DB::raw("CASE
                    WHEN tickets.tipo_solicitud IN ('SOPORTE_IT', 'SOPORTE IT', 'Soporte IT') THEN 'Soporte IT'
                    WHEN tickets.tipo_solicitud IN ('CAMBIO_TONER', 'CAMBIO DE TONER', 'Cambio de Toner') THEN 'Cambio de toner'
                    ELSE tickets.tipo_solicitud
                END AS tipo_solicitud"),
                'tickets.nivel_urgencia',
                'tickets.equipo_afectado',
                'tickets.descripcion_problema',
                'tickets.codigo_impresora',
                'tickets.color_toner',
                'tickets.user_id',
            ]);
    }

    /**
     * Customize the header row in the Excel file.
     */
    public function headings(): array
    {
        return [
            'Fecha',
            'Nombre y Apellido',
            'Departamento',
            'Tipo de solicitud',
            'Nivel de urgencia',
            'Equipo afectado',
            'Descripción del problema',
            'Código de la impresora',
            'Color de tóner',
            'User ID (creador)',
        ];
    }

    /**
     * Auto-size columns and enable wrap for the description column.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Columns A..J (10 columns) -> auto size
                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Enable wrap text for the 'Descripción del problema' column (G)
                $sheet->getStyle('G')->getAlignment()->setWrapText(true);
            },
        ];
    }
}
