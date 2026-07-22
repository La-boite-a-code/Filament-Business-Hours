<?php

declare(strict_types=1);

return [

    'navigation' => [
        'label' => 'Horario de apertura',
    ],

    'page' => [
        'title' => 'Horario de apertura',
        'heading' => 'Horario de apertura',
        'subheading' => 'Gestiona el horario semanal, los cierres excepcionales y los periodos de vacaciones.',
    ],

    'actions' => [
        'save' => 'Guardar',
    ],

    'days' => [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo',
    ],

    'grid' => [
        'heading' => 'Horario semanal',
        'description' => 'Zona horaria: :timezone. La cuadrícula se ajusta a :step minutos.',
        'hint' => 'Arrastra sobre un día para crear una franja, arrastra una franja para moverla, estira sus bordes para redimensionarla. Haz clic en una franja (o clic derecho, o Intro) para escribir horas precisas o eliminarla.',
        'closed' => 'Cerrado',
        'overnight' => '(+1)',
        'copy_day' => 'Copiar este día a…',
        'copy_day_heading' => 'Copiar el horario de «:day» a…',
        'copy' => 'Copiar',
        'clear_day' => 'Vaciar este día',
        'editor' => [
            'edit' => 'Editar la franja',
            'from' => 'De',
            'until' => 'Hasta',
            'delete' => 'Eliminar',
            'done' => 'Hecho',
            'invalid' => 'El final debe ser posterior al inicio.',
        ],
    ],

    'settings' => [
        'label' => 'Ajustes',
        'heading' => 'Ajustes del horario',
        'description' => 'Estos ajustes se aplican al guardar.',
        'apply' => 'Aplicar',
        'timezone' => 'Zona horaria',
        'timezone_placeholder' => 'Zona horaria de la aplicación (:timezone)',
        'timezone_helper' => 'La zona horaria en la que se expresan los horarios.',
        'overflow' => 'Franjas después de medianoche',
        'overflow_helper' => 'Permite franjas que terminan después de medianoche, por ejemplo 20:00-02:00 (indicador «overflow» de spatie/opening-hours). Solo pueden escribirse en el editor de franjas.',
    ],

    'exceptions' => [
        'heading' => 'Días excepcionales',
        'description' => 'Días en los que el establecimiento cierra excepcionalmente, o abre con un horario especial.',
        'empty' => 'Todavía no hay días excepcionales.',
        'add' => 'Añadir una fecha',
        'add_heading' => 'Añadir un día excepcional',
        'edit' => 'Editar',
        'edit_heading' => 'Editar el día excepcional',
        'remove' => 'Eliminar',
        'remove_heading' => '¿Eliminar este día excepcional?',
        'recurring_badge' => 'Cada año',
        'closed_badge' => 'Cerrado',
        'form' => [
            'date' => 'Fecha',
            'recurring' => 'Se repite cada año',
            'recurring_helper' => 'El año de la fecha elegida se ignora.',
            'closed' => 'Cerrado todo el día',
            'hours' => 'Horario de apertura ese día',
            'add_range' => 'Añadir una franja',
            'from' => 'De',
            'until' => 'Hasta',
            'label' => 'Etiqueta',
            'label_placeholder' => 'Navidad, inventario, …',
        ],
    ],

    'vacations' => [
        'heading' => 'Periodos de vacaciones',
        'description' => 'Rangos de fechas en los que el establecimiento permanece cerrado.',
        'empty' => 'Todavía no hay periodos de vacaciones.',
        'add' => 'Añadir un periodo',
        'add_heading' => 'Añadir un periodo de vacaciones',
        'edit' => 'Editar',
        'edit_heading' => 'Editar el periodo de vacaciones',
        'remove' => 'Eliminar',
        'remove_heading' => '¿Eliminar este periodo de vacaciones?',
        'range' => 'Del :from al :until',
        'days_count' => '{1} :count día|[2,*] :count días',
        'fallback_label' => 'Vacaciones',
        'form' => [
            'from' => 'Primer día cerrado',
            'until' => 'Último día cerrado',
            'label' => 'Etiqueta',
            'label_placeholder' => 'Vacaciones de verano, …',
        ],
    ],

    'save' => [
        'success_title' => 'Horario guardado',
        'invalid_title' => 'No se pudo guardar el horario',
        'conflicting_dates' => 'El :date está cubierto dos veces (por «:first» y «:second»). Elimina uno de los dos.',
        'invalid_period' => 'Un periodo de vacaciones tiene una fecha no válida.',
    ],

];
