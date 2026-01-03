<?php
/**
 * Tutorial Definitions for Sikkerjob
 * Defines guided tours and contextual tooltips per page and role
 */

return [
    // Guided Tours - Step-by-step walkthroughs
    'tours' => [
        'view_wo' => [
            'id' => 'view_wo_tour',
            'title' => 'PTW Oversigt',
            'description' => 'Laer at navigere og bruge PTW-oversigten',
            'roles' => ['entreprenor', 'opgaveansvarlig', 'drift', 'admin'],
            'steps' => [
                [
                    'target' => '.advanced-filters-container',
                    'title' => 'Avanceret soegning',
                    'content' => 'Klik her for at aabne avancerede filtre. Du kan soege paa status, firma, dato og mere.',
                    'position' => 'bottom',
                    'roles' => ['opgaveansvarlig', 'drift', 'admin']
                ],
                [
                    'target' => '.filter-controls',
                    'title' => 'Hurtige filtre',
                    'content' => 'Brug disse checkboxe til hurtigt at vise eller skjule PTWer baseret paa status.',
                    'position' => 'bottom'
                ],
                [
                    'target' => '.work-permit-card',
                    'title' => 'PTW Kort',
                    'content' => 'Hvert kort repraesenterer en arbejdstilladelse. Klik paa kortet for at se alle detaljer.',
                    'position' => 'right'
                ],
                [
                    'target' => '.approval-section',
                    'title' => 'Godkendelser',
                    'content' => 'Her kan du se godkendelsesstatus. Groenne flueben betyder godkendt for i dag.',
                    'position' => 'top'
                ],
                [
                    'target' => '.approve-btn',
                    'title' => 'Godkend PTW',
                    'content' => 'Klik her for at godkende PTWen som din rolle. Du skal godkende hver dag arbejdet udfores.',
                    'position' => 'left',
                    'roles' => ['opgaveansvarlig', 'drift', 'entreprenor']
                ],
                [
                    'target' => '.bottom-nav',
                    'title' => 'Mobil navigation',
                    'content' => 'Brug denne menu til hurtigt at navigere mellem sider paa mobil.',
                    'position' => 'top',
                    'mobile_only' => true
                ]
            ]
        ],

        'create_wo' => [
            'id' => 'create_wo_tour',
            'title' => 'Opret ny PTW',
            'description' => 'Laer at oprette en ny arbejdstilladelse',
            'roles' => ['opgaveansvarlig', 'drift', 'admin'],
            'steps' => [
                [
                    'target' => '.pdf-upload-section, #pdf-upload, .drop-zone',
                    'title' => 'PDF Upload',
                    'content' => 'Traek en PDF-fil hertil eller klik for at uploade. Systemet vil automatisk udfylde felterne fra PDFen.',
                    'position' => 'bottom'
                ],
                [
                    'target' => '#work_order_no, input[name="work_order_no"]',
                    'title' => 'PTW Nummer',
                    'content' => 'Indtast det unikke PTW-nummer. Dette bruges til at identificere arbejdstilladelsen.',
                    'position' => 'right'
                ],
                [
                    'target' => '#description, textarea[name="description"]',
                    'title' => 'Beskrivelse',
                    'content' => 'Beskriv arbejdet der skal udfores. Vaer saa specifik som muligt.',
                    'position' => 'right'
                ],
                [
                    'target' => '#entreprenor_firma, select[name="entreprenor_firma"]',
                    'title' => 'Entreprenoer',
                    'content' => 'Vaelg det firma der skal udfoere arbejdet. Listen viser alle registrerede entreprenoerer.',
                    'position' => 'right'
                ],
                [
                    'target' => '#map, .map-container, #location-map',
                    'title' => 'Lokation',
                    'content' => 'Klik paa kortet for at markere hvor arbejdet skal foregaa. Du kan zoome og panorere.',
                    'position' => 'left'
                ],
                [
                    'target' => 'button[type="submit"], .submit-btn, #save-btn',
                    'title' => 'Gem PTW',
                    'content' => 'Naar alle felter er udfyldt, klik her for at gemme arbejdstilladelsen.',
                    'position' => 'top'
                ]
            ]
        ],

        'dashboard' => [
            'id' => 'dashboard_tour',
            'title' => 'Dashboard',
            'description' => 'Faa overblik over systemet',
            'roles' => ['opgaveansvarlig', 'drift', 'admin'],
            'steps' => [
                [
                    'target' => '.stats-grid, .stat-card',
                    'title' => 'Statistik',
                    'content' => 'Her ser du et hurtigt overblik over alle PTWer i systemet fordelt paa status.',
                    'position' => 'bottom'
                ],
                [
                    'target' => '.chart-container, canvas',
                    'title' => 'Grafer',
                    'content' => 'Graferne viser visuelle data om PTW-fordeling og aktivitet over tid.',
                    'position' => 'top'
                ],
                [
                    'target' => '.quick-actions, .action-buttons',
                    'title' => 'Hurtige handlinger',
                    'content' => 'Brug disse knapper til hurtigt at oprette nye PTWer eller se ventende godkendelser.',
                    'position' => 'left'
                ]
            ]
        ],

        'map_wo' => [
            'id' => 'map_wo_tour',
            'title' => 'Kortoversigt',
            'description' => 'Se alle PTWer paa kortet',
            'roles' => ['entreprenor', 'opgaveansvarlig', 'drift', 'admin'],
            'steps' => [
                [
                    'target' => '#map, .leaflet-container',
                    'title' => 'Interaktivt kort',
                    'content' => 'Kortet viser alle PTWer med markorer. Zoom ind/ud med scroll eller knapperne.',
                    'position' => 'right'
                ],
                [
                    'target' => '.leaflet-marker-icon, .marker',
                    'title' => 'PTW Markorer',
                    'content' => 'Hver markor repraesenterer en PTW. Klik paa en markor for at se detaljer.',
                    'position' => 'top'
                ],
                [
                    'target' => '.map-filters, .filter-panel',
                    'title' => 'Filtrer kortet',
                    'content' => 'Brug filtrene til kun at vise bestemte PTWer paa kortet.',
                    'position' => 'left'
                ]
            ]
        ],

        'entreprenor_dashboard' => [
            'id' => 'entreprenor_dashboard_tour',
            'title' => 'Entreprenoer Dashboard',
            'description' => 'Dit personlige overblik',
            'roles' => ['entreprenor'],
            'steps' => [
                [
                    'target' => '.my-ptw-list, .work-orders-section',
                    'title' => 'Dine PTWer',
                    'content' => 'Her ser du alle PTWer tildelt dit firma. Du kan kun se og godkende disse.',
                    'position' => 'bottom'
                ],
                [
                    'target' => '.pending-approvals, .approval-needed',
                    'title' => 'Afventer godkendelse',
                    'content' => 'Disse PTWer venter paa din daglige godkendelse for at arbejdet kan starte.',
                    'position' => 'right'
                ],
                [
                    'target' => '.upload-section, .image-upload',
                    'title' => 'Upload billeder',
                    'content' => 'Naar arbejdet er faerdigt, upload billeder som dokumentation.',
                    'position' => 'left',
                    'roles' => ['entreprenor']
                ]
            ]
        ]
    ],

    // Contextual Tooltips - One-time hints
    'tooltips' => [
        'filter_controls' => [
            'id' => 'tip_filters',
            'target' => '.filter-controls, .quick-filters',
            'content' => 'Brug disse filtre til at vise/skjule PTWer baseret paa deres status.',
            'trigger' => 'click',
            'roles' => ['all']
        ],
        'view_toggle' => [
            'id' => 'tip_view',
            'target' => '.view-btn',
            'content' => 'Skift mellem liste- og kortvisning af PTWer.',
            'trigger' => 'click',
            'roles' => ['all']
        ],
        'approval_badge' => [
            'id' => 'tip_approval_badge',
            'target' => '.approval-badge',
            'content' => 'Groent flueben = godkendt i dag. Graat = afventer godkendelse.',
            'trigger' => 'click',
            'roles' => ['all']
        ],
        'approval_button' => [
            'id' => 'tip_approval',
            'target' => '.btn-approve, .approval-btn',
            'content' => 'Klik for at godkende denne PTW for i dag.',
            'trigger' => 'click',
            'roles' => ['opgaveansvarlig', 'drift', 'entreprenor']
        ],
        'view_button' => [
            'id' => 'tip_view_btn',
            'target' => '.btn-view',
            'content' => 'Klik her for at se alle detaljer om denne PTW.',
            'trigger' => 'click',
            'roles' => ['all']
        ],
        'edit_button' => [
            'id' => 'tip_edit_btn',
            'target' => '.btn-edit',
            'content' => 'Rediger PTWens oplysninger.',
            'trigger' => 'click',
            'roles' => ['opgaveansvarlig', 'drift', 'admin']
        ],
        'notification_bell' => [
            'id' => 'tip_notifications',
            'target' => '.notification-bell',
            'content' => 'Se dine notifikationer om godkendelser og opdateringer.',
            'trigger' => 'click',
            'roles' => ['all']
        ],
        'status_badge' => [
            'id' => 'tip_status',
            'target' => '.status-planlagt, .status-aktiv, .status-afsluttet',
            'content' => 'Status viser om PTWen er planlagt, aktiv eller afsluttet.',
            'trigger' => 'click',
            'roles' => ['all']
        ],
        'work_status' => [
            'id' => 'tip_work_status',
            'target' => '.btn-work, .work-status-badge',
            'content' => 'Viser om arbejdet er i gang, paa pause eller stoppet for i dag.',
            'trigger' => 'click',
            'roles' => ['all']
        ]
    ],

    // Role-specific tutorial paths (recommended order)
    'role_paths' => [
        'entreprenor' => [
            'recommended_tours' => ['view_wo_tour', 'map_wo_tour', 'entreprenor_dashboard_tour'],
            'videos' => [
                ['id' => 'daglig_godkendelse', 'title' => 'Daglig godkendelse', 'duration' => 120],
                ['id' => 'upload_billeder', 'title' => 'Upload af billeder', 'duration' => 90],
                ['id' => 'brug_kort', 'title' => 'Brug af kortet', 'duration' => 120]
            ]
        ],
        'opgaveansvarlig' => [
            'recommended_tours' => ['view_wo_tour', 'create_wo_tour', 'dashboard_tour', 'map_wo_tour'],
            'videos' => [
                ['id' => 'opret_ptw', 'title' => 'Opret ny PTW', 'duration' => 180],
                ['id' => 'pdf_upload', 'title' => 'PDF upload og parsing', 'duration' => 120],
                ['id' => 'godkendelsesflow', 'title' => 'Godkendelsesworkflow', 'duration' => 150]
            ]
        ],
        'drift' => [
            'recommended_tours' => ['view_wo_tour', 'create_wo_tour', 'map_wo_tour', 'dashboard_tour'],
            'videos' => [
                ['id' => 'ptw_oversigt', 'title' => 'PTW Oversigt', 'duration' => 120],
                ['id' => 'daglige_operationer', 'title' => 'Daglige operationer', 'duration' => 150],
                ['id' => 'sikkerhedstjek', 'title' => 'Sikkerhedstjek', 'duration' => 120]
            ]
        ],
        'admin' => [
            'recommended_tours' => ['view_wo_tour', 'create_wo_tour', 'dashboard_tour'],
            'videos' => [
                ['id' => 'admin_oversigt', 'title' => 'Admin oversigt', 'duration' => 180],
                ['id' => 'brugerstyring', 'title' => 'Brugerstyring', 'duration' => 150],
                ['id' => 'systemindstillinger', 'title' => 'Systemindstillinger', 'duration' => 120]
            ]
        ]
    ],

    // Page to tour mapping
    'page_tours' => [
        'view_wo.php' => 'view_wo',
        'create_wo.php' => 'create_wo',
        'dashboard.php' => 'dashboard',
        'map_wo.php' => 'map_wo'
    ]
];
