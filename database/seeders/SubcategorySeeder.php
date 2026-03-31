<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class SubcategorySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'FABRICACION' => [
                'MAQUINA ROTATIVA',
                'MAQUINA ELECTRICA',
                'ARTEFACTO ELECTRICO',
                'ARTEFACTO ELETRÓNICO',
                'ARTEFACTO NEUMATICO',
                'ARTEFACTO HIDRAULICO',
                'MATERIAL MECANICO',
                'MATERIAL ELECTRICO',
                'MARTERIAL ELECTRONICO',
                'INSTRUMENTO DE CALIBRACION',
                'INSTRUMENTO DE MEDICION ELECTRICA',
                'INTRUMENTO DE MEDICION MECANICA',
                'INSTRUMENTO DE MEDICION ELECTRONICA',
                'MANUAL',
                'IDENTIFICACION',
            ],
            'CONSUMIBLES' => [
                'PAPELERIA',
                'PRODUCTO DE LIMPIEZA',
                'EQUIPOS DE PROTECCIÓN DE USO DIARIO',
                'PILAS',
                'ALIMENTOS',
                'JUEGOS',
                'DECORACIÓN',
            ],
            'INFORMATICA' => [
                'COMPUTADORA',
                'PERIFÉRICOS',
                'PLACAS Y COMPONENTES',
                'DISPOSITIVO DE ALMACENAMIENTO',
                'IMPRESORAS',
            ],
            'TELECOMUNICACIONES' => [
                'EQUIPO DE RED',
                'ACCESORIO DE RED',
                'ACCESORIO ELECTRÓNICO',
                'ACCESORIO',
            ],
            'MEDICAMENTOS' => [
                'ANALGESICO',
                'ANTIALERGICO',
                'ANTIBIOTICO',
                'PROTECTOR GASTRICO',
                'ANTISEPTICO',
                'SOLUCION',
                'PRIMEROS AUXILIOS',
                'INSTRUMENTO QUIRURGICO',
                'ANTIINFLAMATORIO',
                'ANTIINFECCIOSO BUCOFARÍNGEO',
                'ANTIGRIPAL',
                'ANTIDIARREICO',
                'ANTIHISTAMINICO',
                'OFTÁLMICO',
            ],
            'EQUIPO MEDICO' => [
                'NEBULIZADOR',
                'RECOLECTOR',
                'OXÍGENO',
                'AMBULANCIA',
                'CARETA',
            ],
            'SOLDADURA' => [
                'EQUIPO DE PROTECCIÓN',
                'EQUIPOS Y ACCESORIOS',
                'MATERIAL DE APORTE',
                'EQUIPO DE PROTECCION',
            ],
            'LABORATORIO' => [
                'DETERMINACIÓN DE GRADOS API',
                'INSTRUMENTO DE MEDICIÓN',
                'DETERMINACIÓN DE GRADOS FAHRENHEIT',
                'REACTIVO QUÍMICO',
                'INSTRUMENTO DE MEDICIÓN Y TRANSFERENCIA DE VOLUMEN',
                'TOMA DE MUESTRA',
            ],
            'HERRAMIENTAS' => [
                'HERRAMIENTA DE MANO',
                'HERRAMIENTA DE ABRASIÓN',
                'MATERIAL ELECTRICO',
                'ACCESORIO PARA PINTURA',
                'HERRAMIENTA ELÉCTRICA',
                'MATERIAL DE CONSTRUCCIÓN',
                'INSTRUMENTO DE MEDICIÓN',
                'HERRAMIENTA DE CORTE',
                'MATERIAL DE REFRIGERACIÓN',
                'MATERIAL PVC',
                'ALMACENAMIENTO',
                'PINTURA',
                'HERRAMIENTA ELECTRONICA',
                'HERRAMIENTA MECANICA',
            ],
            'UTENSILIOS DE COCINA' => [
                'ALMACENAMIENTO TÉRMICO',
                'PREPARACIÓN DE ALIMENTOS',
                'COCCIÓN',
                'ELECTRODOMÉSTICO',
                'CONTENEDOR DE LÍQUIDOS DE LIMPIEZA',
            ],
            'PRODUCTOS QUIMICOS' => [
                'DILUYENTES Y SOLVENTES',
                'GLICERINA',
                'LUBRICACION',
            ],
            'ILUMINACION' => [
                'ILUMINACION FIJA',
                'ILUMINACION PORTATIL',
            ],
            'SISTEMA DE SEGURIDAD' => [
                'DETECTOR DE INCENDIOS',
                'ALARMAS',
                'CONTROL DE ACCESO',
                'SEÑALIZACIONES',
                'EQUIPO CONTRA INCENDIOS',
                'VIGILANCIA Y MONITOREO',
                'ANTIDERRAMES',
            ],
            'EPP' => [
                'PROTECCIÓN DE CABEZA',
                'PROTECCIÓN FACIAL',
                'PROTECCIÓN AUDITIVA',
                'PROTECCIÓN DE PIES',
                'PROTECCIÓN VISUAL',
                'EQUIPO DE PROTECCION ACUATICO',
                'PROTECCION CORPORAL',
            ],
            'ELECTRONICA DE CONSUMO' => [
                'EQUIPO DE SONIDO',
                'EQUIPO DE VIDEO',
                'FUENTE DE PODER',
                'TELEVISOR',
            ],
            'VEHICULO' => [
                'REPUESTO',
                'FLUIDOS DE OPERACIÓN',
                'HERRAMIENTA DE ELEVACIÓN',
                'ACCESORIO',
                'SEGREGACION DE COMBUSTIBLE',
            ],
            'ALS' => [],
        ];

        $created = 0;

        foreach ($catalog as $categoryName => $subcategories) {
            $category = Category::query()->where('name', $categoryName)->first();

            if (! $category) {
                $this->command?->warn("Categoria no encontrada: {$categoryName}");

                continue;
            }

            foreach ($subcategories as $subcategoryName) {
                Subcategory::firstOrCreate([
                    'name' => $subcategoryName,
                    'category_id' => $category->id,
                ]);
                $created++;
            }
        }

        $this->command?->info("Subcategorias procesadas: {$created}.");
    }
}
