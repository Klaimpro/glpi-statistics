<?php

namespace GlpiPlugin\Dsistats;

use CommonITILActor;
use DateTimeImmutable;
use InvalidArgumentException;
use Ticket;

/**
 * Build diagnostic data for the DSI dashboard.
 */
class StatsService
{
    private const MIN_YEAR = 2000;
    private const MAX_YEAR = 2100;

    private const TARGET_GROUPS = [
        'infra' => [
            'label' => 'INFRA',
            'name' => 'INFRA',
            'completename' => 'INFRA',
        ],
        'etudes' => [
            'label' => 'ETUDES',
            'name' => 'ETUDES',
            'completename' => 'ETUDES',
        ],
        'reseau_secu' => [
            'label' => 'Réseau Sécu',
            'name' => 'RESEAU & SECURITE',
            'completename' => 'INFRA > RESEAU & SECURITE',
        ],
    ];

    private const TICKET_TYPES = [
        Ticket::INCIDENT_TYPE => 'Incident',
        Ticket::DEMAND_TYPE   => 'Demande',
    ];

    private const TARGETS = [
        'infra' => [
            Ticket::INCIDENT_TYPE => [
                'solve' => 50400,
            ],
        ],
        'etudes' => [
            Ticket::DEMAND_TYPE => [
                'take' => 252000,
            ],
        ],
        'reseau_secu' => [
            Ticket::INCIDENT_TYPE => [
                'solve' => 252000,
            ],
        ],
    ];

    private const GRAPH_METRICS = [
        'solve' => 'Résolution',
        'take'  => 'Prise en compte',
    ];

    /**
     * Month labels used by the month selector and period display.
     */
    private const MONTHS = [
        1  => 'Janvier',
        2  => 'Février',
        3  => 'Mars',
        4  => 'Avril',
        5  => 'Mai',
        6  => 'Juin',
        7  => 'Juillet',
        8  => 'Août',
        9  => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Décembre',
    ];

    public static function getMonthChoices(): array
    {
        return self::MONTHS;
    }

    public static function getDefaultFilters(): array
    {
        $now = new DateTimeImmutable('now');

        return self::buildFilters(
            1,
            (int) $now->format('Y'),
            (int) $now->format('n'),
            (int) $now->format('Y')
        );
    }

    public static function getGraphDefaults(): array
    {
        return [
            'selected_groups'  => array_keys(self::TARGET_GROUPS),
            'selected_types'   => array_keys(self::TICKET_TYPES),
            'selected_metrics' => array_keys(self::GRAPH_METRICS),
        ];
    }

    /**
     * Validate and normalize raw GET parameters.
     */
    public static function normalizeFilters(array $input): array
    {
        $defaults = self::getDefaultFilters();

        $from_month = self::validateMonth($input['from_month'] ?? $defaults['from_month'], 'from_month');
        $from_year  = self::validateYear($input['from_year'] ?? $defaults['from_year'], 'from_year');
        $to_month   = self::validateMonth($input['to_month'] ?? $defaults['to_month'], 'to_month');
        $to_year    = self::validateYear($input['to_year'] ?? $defaults['to_year'], 'to_year');

        $filters = self::buildFilters($from_month, $from_year, $to_month, $to_year);

        if ($filters['from_period_sort'] > $filters['to_period_sort']) {
            throw new InvalidArgumentException(__('La période de début doit être antérieure ou égale à la période de fin.', 'dsistats'));
        }

        return $filters;
    }

    /**
     * Return the full dataset expected by the dashboard template.
     */
    public static function getDashboardData(array $filters): array
    {
        $groups = self::findTargetGroups();
        $ticket_rows = self::loadTicketRows($filters, $groups);
        $rows = self::buildMonthlyRows($filters, $groups, $ticket_rows);

        return [
            'filters' => $filters,
            'groups' => $rows,
            'period_label' => $filters['period_label'],
            'visibility_note' => __('Les comptages sont limités aux tickets visibles dans les entités actives et selon les droits tickets du profil courant.', 'dsistats'),
        ];
    }

    public static function normalizeGraphFilters(array $input): array
    {
        $filters = self::normalizeFilters($input);
        $defaults = self::getGraphDefaults();

        $filters['selected_groups'] = self::validateSelection(
            $input['groups'] ?? $defaults['selected_groups'],
            array_keys(self::TARGET_GROUPS),
            $defaults['selected_groups']
        );
        $filters['selected_types'] = array_map(
            'intval',
            self::validateSelection(
                $input['types'] ?? array_map('strval', $defaults['selected_types']),
                array_map('strval', array_keys(self::TICKET_TYPES)),
                array_map('strval', $defaults['selected_types'])
            )
        );
        $filters['selected_metrics'] = self::validateSelection(
            $input['metrics'] ?? $defaults['selected_metrics'],
            array_keys(self::GRAPH_METRICS),
            $defaults['selected_metrics']
        );

        return $filters;
    }

    public static function getGraphData(array $filters): array
    {
        $groups = self::findTargetGroups();
        $ticket_rows = self::loadTicketRows($filters, $groups);
        $months = self::buildMonthSequence($filters);
        $matrix = self::buildAggregationMatrix($months, $ticket_rows);

        return [
            'filters'         => $filters,
            'period_label'    => $filters['period_label'],
            'visibility_note' => __('Les comptages sont limités aux tickets visibles dans les entités actives et selon les droits tickets du profil courant.', 'dsistats'),
            'group_choices'   => self::buildGraphGroupChoices($groups),
            'type_choices'    => self::buildTypeChoices(),
            'metric_choices'  => self::buildMetricChoices(),
            'chart'           => self::buildChartDataset($filters, $groups, $months, $matrix),
        ];
    }

    private static function buildMonthlyRows(array $filters, array $groups, array $ticket_rows): array
    {
        $group_blocks = [];
        $months = self::buildMonthSequence($filters);
        $matrix = self::buildAggregationMatrix($months, $ticket_rows);

        foreach (self::TARGET_GROUPS as $group_key => $definition) {
            $group = $groups[$group_key];
            $average_rows = [];
            $volume_rows = [];
            $period_totals = [
                Ticket::INCIDENT_TYPE => [
                    'ticket_count' => 0,
                    'take_sum'     => 0,
                    'take_count'   => 0,
                    'solve_sum'    => 0,
                    'solve_count'  => 0,
                ],
                Ticket::DEMAND_TYPE => [
                    'ticket_count' => 0,
                    'take_sum'     => 0,
                    'take_count'   => 0,
                    'solve_sum'    => 0,
                    'solve_count'  => 0,
                ],
            ];

            foreach ($months as $month_key => $month_data) {
                $incident_cell = $matrix[$month_key][$group_key][Ticket::INCIDENT_TYPE];
                $request_cell  = $matrix[$month_key][$group_key][Ticket::DEMAND_TYPE];

                $average_rows[] = [
                    'month_label'      => $month_data['label'],
                    'incident_solve'   => self::buildMetricCell($group_key, Ticket::INCIDENT_TYPE, 'solve', $incident_cell),
                    'request_solve'    => self::buildMetricCell($group_key, Ticket::DEMAND_TYPE, 'solve', $request_cell),
                    'incident_take'    => self::buildMetricCell($group_key, Ticket::INCIDENT_TYPE, 'take', $incident_cell),
                    'request_take'     => self::buildMetricCell($group_key, Ticket::DEMAND_TYPE, 'take', $request_cell),
                ];

                $volume_rows[] = [
                    'month_label'     => $month_data['label'],
                    'incident_count'  => $incident_cell['ticket_count'],
                    'request_count'   => $request_cell['ticket_count'],
                    'total_count'     => $incident_cell['ticket_count'] + $request_cell['ticket_count'],
                ];

                foreach ([Ticket::INCIDENT_TYPE, Ticket::DEMAND_TYPE] as $type_id) {
                    $period_totals[$type_id]['ticket_count'] += $matrix[$month_key][$group_key][$type_id]['ticket_count'];
                    $period_totals[$type_id]['take_sum'] += $matrix[$month_key][$group_key][$type_id]['take_sum'];
                    $period_totals[$type_id]['take_count'] += $matrix[$month_key][$group_key][$type_id]['take_count'];
                    $period_totals[$type_id]['solve_sum'] += $matrix[$month_key][$group_key][$type_id]['solve_sum'];
                    $period_totals[$type_id]['solve_count'] += $matrix[$month_key][$group_key][$type_id]['solve_count'];
                }
            }

            $group_blocks[] = [
                'group_label'          => $definition['label'],
                'group_status'         => $group['status'],
                'group_status_message' => $group['status_message'],
                'group_id'             => $group['id'],
                'average_rows'         => $average_rows,
                'period_average_row'   => [
                    'label'            => __('Moyenne période', 'dsistats'),
                    'incident_solve'   => self::buildMetricCell($group_key, Ticket::INCIDENT_TYPE, 'solve', $period_totals[Ticket::INCIDENT_TYPE]),
                    'request_solve'    => self::buildMetricCell($group_key, Ticket::DEMAND_TYPE, 'solve', $period_totals[Ticket::DEMAND_TYPE]),
                    'incident_take'    => self::buildMetricCell($group_key, Ticket::INCIDENT_TYPE, 'take', $period_totals[Ticket::INCIDENT_TYPE]),
                    'request_take'     => self::buildMetricCell($group_key, Ticket::DEMAND_TYPE, 'take', $period_totals[Ticket::DEMAND_TYPE]),
                ],
                'volume_rows'          => $volume_rows,
                'volume_totals'        => [
                    'label'          => 'TOTAL',
                    'incident_count' => $period_totals[Ticket::INCIDENT_TYPE]['ticket_count'],
                    'request_count'  => $period_totals[Ticket::DEMAND_TYPE]['ticket_count'],
                    'total_count'    => $period_totals[Ticket::INCIDENT_TYPE]['ticket_count'] + $period_totals[Ticket::DEMAND_TYPE]['ticket_count'],
                ],
            ];
        }

        return $group_blocks;
    }

    private static function buildAggregationMatrix(array $months, array $ticket_rows): array
    {
        $matrix = [];

        foreach ($months as $month_key => $month_data) {
            foreach (self::TARGET_GROUPS as $group_key => $definition) {
                foreach (self::TICKET_TYPES as $type_id => $type_label) {
                    $matrix[$month_key][$group_key][$type_id] = [
                        'ticket_count' => 0,
                        'take_sum'     => 0,
                        'take_count'   => 0,
                        'solve_sum'    => 0,
                        'solve_count'  => 0,
                    ];
                }
            }
        }

        foreach ($ticket_rows as $ticket_row) {
            $group_key = $ticket_row['group_key'];
            $month_key = $ticket_row['month_key'];
            $type_id   = $ticket_row['type'];

            if (!isset($matrix[$month_key][$group_key][$type_id])) {
                continue;
            }

            $matrix[$month_key][$group_key][$type_id]['ticket_count']++;

            if ($ticket_row['take_delay'] !== null && $ticket_row['take_delay'] >= 0) {
                $matrix[$month_key][$group_key][$type_id]['take_sum'] += $ticket_row['take_delay'];
                $matrix[$month_key][$group_key][$type_id]['take_count']++;
            }

            if ($ticket_row['solve_delay'] !== null && $ticket_row['solve_delay'] >= 0) {
                $matrix[$month_key][$group_key][$type_id]['solve_sum'] += $ticket_row['solve_delay'];
                $matrix[$month_key][$group_key][$type_id]['solve_count']++;
            }
        }

        return $matrix;
    }

    private static function buildMonthSequence(array $filters): array
    {
        $months = [];
        $cursor = new DateTimeImmutable($filters['from_date']);
        $end    = new DateTimeImmutable($filters['to_date_exclusive']);

        while ($cursor < $end) {
            $year = (int) $cursor->format('Y');
            $month = (int) $cursor->format('n');
            $month_key = $cursor->format('Y-m');

            $months[$month_key] = [
                'label' => self::MONTHS[$month] . ' ' . $year,
            ];

            $cursor = $cursor->modify('first day of next month');
        }

        return $months;
    }

    private static function formatAverageDuration(int $sum, int $count): string
    {
        if ($count === 0) {
            return 'N/A';
        }

        $average = (int) round($sum / $count);
        $hours = intdiv($average, HOUR_TIMESTAMP);
        $minutes = intdiv($average % HOUR_TIMESTAMP, MINUTE_TIMESTAMP);
        $display = sprintf('%dh %02dm', $hours, $minutes);

        if ($average >= (7 * HOUR_TIMESTAMP)) {
            $business_days = number_format($average / (7 * HOUR_TIMESTAMP), 2, ',', '');
            $display .= sprintf(' (%s j)', $business_days);
        }

        return $display;
    }

    private static function computeAverageSeconds(int $sum, int $count): ?int
    {
        if ($count === 0) {
            return null;
        }

        return (int) round($sum / $count);
    }

    private static function resolveMetricStatus(string $group_key, int $type_id, string $metric, int $sum, int $count): string
    {
        $threshold = self::TARGETS[$group_key][$type_id][$metric] ?? null;
        $average_seconds = self::computeAverageSeconds($sum, $count);

        if ($threshold === null || $average_seconds === null) {
            return 'neutral';
        }

        return $average_seconds < $threshold ? 'success' : 'danger';
    }

    private static function buildMetricCell(string $group_key, int $type_id, string $metric, array $cell): array
    {
        $sum_key = $metric . '_sum';
        $count_key = $metric . '_count';

        return [
            'display'         => self::formatAverageDuration($cell[$sum_key], $cell[$count_key]),
            'status'          => self::resolveMetricStatus($group_key, $type_id, $metric, $cell[$sum_key], $cell[$count_key]),
            'average_seconds' => self::computeAverageSeconds($cell[$sum_key], $cell[$count_key]),
            'count'           => $cell[$count_key],
        ];
    }

    private static function buildGraphGroupChoices(array $groups): array
    {
        $choices = [];

        foreach (self::TARGET_GROUPS as $group_key => $definition) {
            $choices[] = [
                'key'            => $group_key,
                'label'          => $definition['label'],
                'status'         => $groups[$group_key]['status'],
                'status_message' => $groups[$group_key]['status_message'],
            ];
        }

        return $choices;
    }

    private static function buildTypeChoices(): array
    {
        $choices = [];

        foreach (self::TICKET_TYPES as $type_id => $label) {
            $choices[] = [
                'key'   => $type_id,
                'label' => $label,
            ];
        }

        return $choices;
    }

    private static function buildMetricChoices(): array
    {
        $choices = [];

        foreach (self::GRAPH_METRICS as $metric_key => $label) {
            $choices[] = [
                'key'   => $metric_key,
                'label' => $label,
            ];
        }

        return $choices;
    }

    private static function buildChartDataset(array $filters, array $groups, array $months, array $matrix): array
    {
        $month_keys = array_keys($months);
        $series = [];

        foreach ($filters['selected_groups'] as $group_key) {
            if (!isset(self::TARGET_GROUPS[$group_key])) {
                continue;
            }

            foreach ($filters['selected_types'] as $type_id) {
                if (!isset(self::TICKET_TYPES[$type_id])) {
                    continue;
                }

                foreach ($filters['selected_metrics'] as $metric_key) {
                    if (!isset(self::GRAPH_METRICS[$metric_key])) {
                        continue;
                    }

                    $values = [];
                    foreach ($month_keys as $month_key) {
                        $cell = $matrix[$month_key][$group_key][$type_id];
                        $sum_key = $metric_key . '_sum';
                        $count_key = $metric_key . '_count';
                        $average_seconds = self::computeAverageSeconds($cell[$sum_key], $cell[$count_key]);
                        $values[] = $average_seconds !== null ? round($average_seconds / HOUR_TIMESTAMP, 2) : null;
                    }

                    $threshold_seconds = self::TARGETS[$group_key][$type_id][$metric_key] ?? null;
                    $series[] = [
                        'label'           => sprintf(
                            '%s - %s - %s',
                            self::TARGET_GROUPS[$group_key]['label'],
                            self::TICKET_TYPES[$type_id],
                            self::GRAPH_METRICS[$metric_key]
                        ),
                        'values'          => $values,
                        'metric'          => $metric_key,
                        'threshold_hours' => $threshold_seconds !== null ? round($threshold_seconds / HOUR_TIMESTAMP, 2) : null,
                    ];
                }
            }
        }

        return [
            'months'       => array_values(array_map(static fn(array $month): string => $month['label'], $months)),
            'month_keys'   => $month_keys,
            'series'       => $series,
            'has_series'   => count($series) > 0,
        ];
    }

    private static function validateSelection(mixed $raw_selection, array $allowed_values, array $default_values): array
    {
        if (!is_array($raw_selection)) {
            $raw_selection = [$raw_selection];
        }

        $selected = array_values(array_unique(array_map('strval', array_filter($raw_selection, static fn($value): bool => is_scalar($value)))));
        $allowed  = array_map('strval', $allowed_values);
        $filtered = array_values(array_intersect($selected, $allowed));

        return count($filtered) > 0 ? $filtered : array_map('strval', $default_values);
    }

    private static function buildFilters(int $from_month, int $from_year, int $to_month, int $to_year): array
    {
        $from_date = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $from_year, $from_month));
        $to_date = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $to_year, $to_month));
        $to_date_exclusive = $to_date->modify('first day of next month');

        return [
            'from_month' => $from_month,
            'from_year' => $from_year,
            'to_month' => $to_month,
            'to_year' => $to_year,
            'from_date' => $from_date->format('Y-m-d H:i:s'),
            'to_date_exclusive' => $to_date_exclusive->format('Y-m-d H:i:s'),
            'from_period_sort' => ($from_year * 100) + $from_month,
            'to_period_sort' => ($to_year * 100) + $to_month,
            'from_label' => self::MONTHS[$from_month] . ' ' . $from_year,
            'to_label' => self::MONTHS[$to_month] . ' ' . $to_year,
            'period_label' => self::MONTHS[$from_month] . ' ' . $from_year . ' → ' . self::MONTHS[$to_month] . ' ' . $to_year,
        ];
    }

    private static function validateMonth(mixed $value, string $field): int
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,2}$/', (string) $value)) {
            throw new InvalidArgumentException(sprintf(__('Mois invalide fourni pour %s.', 'dsistats'), $field));
        }

        $month = (int) $value;
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(sprintf(__('Le mois %s doit être compris entre 1 et 12.', 'dsistats'), $field));
        }

        return $month;
    }

    private static function validateYear(mixed $value, string $field): int
    {
        if (!is_scalar($value) || !preg_match('/^\d{4}$/', (string) $value)) {
            throw new InvalidArgumentException(sprintf(__('Année invalide fournie pour %s.', 'dsistats'), $field));
        }

        $year = (int) $value;
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            throw new InvalidArgumentException(
                sprintf(
                    __('L’année %1$s doit être comprise entre %2$d et %3$d.', 'dsistats'),
                    $field,
                    self::MIN_YEAR,
                    self::MAX_YEAR
                )
            );
        }

        return $year;
    }

    /**
     * Resolve exact GLPI groups.
     *
     * Groups are hierarchical in GLPI because Group extends CommonTreeDropdown.
     * For this diagnostic step we only match the exact requested group, without
     * expanding to child groups.
     */
    private static function findTargetGroups(): array
    {
        global $DB;

        $groups = [];
        foreach (self::TARGET_GROUPS as $key => $definition) {
            $groups[$key] = [
                'id' => 0,
                'status' => 'missing',
                'status_message' => __('Groupe introuvable', 'dsistats'),
                'matched_name' => '',
                'matched_completename' => '',
            ];
        }

        foreach (self::TARGET_GROUPS as $key => $definition) {
            $iterator = $DB->request([
                'SELECT' => [
                    'glpi_groups.id',
                    'glpi_groups.name',
                    'glpi_groups.completename',
                    'glpi_groups.groups_id',
                    'glpi_groups.level',
                ],
                'FROM' => 'glpi_groups',
                'WHERE' => [
                    'glpi_groups.name'         => $definition['name'],
                    'glpi_groups.completename' => $definition['completename'],
                ],
            ]);

            $matches = iterator_to_array($iterator, false);

            if (count($matches) === 1) {
                $groups[$key] = [
                    'id' => (int) $matches[0]['id'],
                    'status' => 'found',
                    'status_message' => '',
                    'matched_name' => (string) $matches[0]['name'],
                    'matched_completename' => (string) $matches[0]['completename'],
                ];
                continue;
            }

            if (count($matches) > 1) {
                $groups[$key] = [
                    'id' => 0,
                    'status' => 'ambiguous',
                    'status_message' => __('Plusieurs groupes exacts trouvés', 'dsistats'),
                    'matched_name' => '',
                    'matched_completename' => '',
                ];
            }
        }

        return $groups;
    }

    /**
     * Load ticket rows using GLPI query builder only.
     *
     * Each returned row is unique for one ticket and one assigned group.
     * This protects counts and averages from LEFT JOIN duplication caused by ACL.
     */
    private static function loadTicketRows(array $filters, array $groups): array
    {
        global $DB;

        $group_ids = [];
        $group_key_by_id = [];

        foreach ($groups as $group_key => $group) {
            if ($group['status'] !== 'found') {
                continue;
            }

            $group_ids[] = $group['id'];
            $group_key_by_id[$group['id']] = $group_key;
        }

        if (count($group_ids) === 0) {
            return [];
        }

        $criteria = [
            'DISTINCT' => true,
            'SELECT' => [
                'glpi_tickets.id AS ticket_id',
                'glpi_groups_tickets.groups_id',
                'glpi_tickets.type',
                'glpi_tickets.date',
                'glpi_tickets.takeintoaccount_delay_stat',
                'glpi_tickets.solve_delay_stat',
            ],
            'FROM' => 'glpi_tickets',
            'INNER JOIN' => [
                'glpi_groups_tickets' => [
                    'ON' => [
                        'glpi_groups_tickets' => 'tickets_id',
                        'glpi_tickets' => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'glpi_tickets.is_deleted' => 0,
                'glpi_tickets.type' => [Ticket::INCIDENT_TYPE, Ticket::DEMAND_TYPE],
                'glpi_groups_tickets.groups_id' => $group_ids,
                'glpi_groups_tickets.type' => CommonITILActor::ASSIGN,
                'glpi_tickets.date' => ['>=', $filters['from_date']],
                [
                    'glpi_tickets.date' => ['<', $filters['to_date_exclusive']],
                ],
            ],
            'ORDERBY' => [
                'glpi_tickets.date',
                'glpi_groups_tickets.groups_id',
                'glpi_tickets.type',
            ],
        ];

        self::appendWhereCriteria($criteria['WHERE'], \getEntitiesRestrictCriteria(Ticket::getTable()));
        self::mergeProfileCriteria($criteria, Ticket::getCriteriaFromProfile());

        $rows = [];
        foreach ($DB->request($criteria) as $row) {
            $group_id = (int) $row['groups_id'];
            $group_key = $group_key_by_id[$group_id] ?? null;

            if ($group_key === null) {
                continue;
            }

            $created_at = new DateTimeImmutable($row['date']);

            $rows[] = [
                'ticket_id'   => (int) $row['ticket_id'],
                'group_key'   => $group_key,
                'type'        => (int) $row['type'],
                'month_key'   => $created_at->format('Y-m'),
                'take_delay'  => $row['takeintoaccount_delay_stat'] !== null ? (int) $row['takeintoaccount_delay_stat'] : null,
                'solve_delay' => $row['solve_delay_stat'] !== null ? (int) $row['solve_delay_stat'] : null,
            ];
        }

        return $rows;
    }

    private static function mergeProfileCriteria(array &$criteria, array $profile_criteria): void
    {
        if (isset($profile_criteria['LEFT JOIN']) && is_array($profile_criteria['LEFT JOIN'])) {
            $criteria['LEFT JOIN'] = ($criteria['LEFT JOIN'] ?? []) + $profile_criteria['LEFT JOIN'];
        }

        if (isset($profile_criteria['WHERE']) && is_array($profile_criteria['WHERE'])) {
            self::appendWhereCriteria($criteria['WHERE'], $profile_criteria['WHERE']);
        }
    }

    private static function appendWhereCriteria(array &$target_where, array $criteria_to_add): void
    {
        foreach ($criteria_to_add as $key => $value) {
            if (is_int($key)) {
                $target_where[] = $value;
                continue;
            }

            $target_where[$key] = $value;
        }
    }
}
