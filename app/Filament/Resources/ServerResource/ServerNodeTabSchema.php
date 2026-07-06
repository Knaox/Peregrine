<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServerResource;

use App\Actions\Pelican\ResolveServerNodeAction;
use App\Models\Node;
use App\Models\Server;
use App\Services\Wings\DTOs\NodeHealthReport;
use App\Services\Wings\NodeHealthService;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\HtmlString;

/**
 * "Node" tab on the admin server page: which Wings node hosts the server,
 * plus a live health verdict (three-stage probe, 30s cache). Admins also
 * see the raw technical detail (Wings error bodies, request ids) that the
 * player-facing surface deliberately hides.
 */
final class ServerNodeTabSchema
{
    private const SEVERITY_COLORS = [
        'ok' => '#22c55e',
        'warning' => '#f59e0b',
        'critical' => '#ef4444',
    ];

    public static function make(): Tab
    {
        return Tab::make(__('admin/servers.node.tab'))
            ->icon('heroicon-o-server-stack')
            ->visible(fn (?Server $record) => $record !== null)
            ->schema([
                Placeholder::make('node_panel')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->content(fn (?Server $record) => $record ? self::render($record) : new HtmlString('')),
            ]);
    }

    private static function render(Server $server): HtmlString
    {
        $node = app(ResolveServerNodeAction::class)($server);

        if ($node === null) {
            return new HtmlString(
                '<p style="opacity:.7;margin:0">'.e(__('admin/servers.node.not_linked')).'</p>'
            );
        }

        $health = app(NodeHealthService::class);
        $report = $server->pelican_uuid
            ? $health->checkServerOnNode($node, $server->pelican_uuid)
            : $health->checkNode($node);

        return new HtmlString(self::statusBlock($report).self::factsGrid($node, $report));
    }

    private static function statusBlock(NodeHealthReport $report): string
    {
        $color = self::SEVERITY_COLORS[$report->status->severity()] ?? self::SEVERITY_COLORS['warning'];
        $label = e(__('admin/servers.node.statuses.'.$report->status->value));
        $hint = e(__('admin/servers.node.status_hints.'.$report->status->value));

        $meta = [];
        if ($report->latencyMs !== null) {
            $meta[] = e(__('admin/servers.node.latency', ['ms' => $report->latencyMs]));
        }
        if ($report->checkedAt !== null) {
            $meta[] = e(__('admin/servers.node.checked_at', ['time' => $report->checkedAt->diffForHumans()]));
        }
        $metaHtml = $meta === [] ? '' : '<span style="opacity:.6;font-size:.85em">· '.implode(' · ', $meta).'</span>';

        $detailHtml = '';
        if ($report->detail !== null && $report->status->isProblem()) {
            $detailHtml = '<code style="display:block;padding:8px 10px;border-radius:8px;'
                .'background:rgba(127,127,127,.12);font-size:.8em;white-space:pre-wrap;word-break:break-word">'
                .e($report->detail).'</code>';
        }

        return '<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px">'
            .'<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">'
            ."<span style=\"width:10px;height:10px;border-radius:9999px;background:{$color};box-shadow:0 0 6px {$color}\"></span>"
            ."<span style=\"font-weight:600\">{$label}</span>{$metaHtml}"
            .'</div>'
            ."<p style=\"opacity:.75;margin:0;font-size:.9em\">{$hint}</p>"
            .$detailHtml
            .'</div>';
    }

    private static function factsGrid(Node $node, NodeHealthReport $report): string
    {
        $facts = [
            __('admin/_shell.fields.name') => $node->name,
            'FQDN' => $node->fqdn.':'.$node->daemon_listen.' ('.$node->scheme.')',
            __('admin/servers.node.location') => $node->location ?: '—',
            __('admin/servers.node.pelican_node_id') => (string) $node->pelican_node_id,
            __('admin/servers.node.wings_version') => $report->wingsVersion ?? '—',
            __('admin/servers.node.maintenance') => $node->maintenance_mode
                ? __('admin/servers.node.maintenance_on')
                : __('admin/servers.node.maintenance_off'),
        ];

        $cells = '';
        foreach ($facts as $label => $value) {
            $cells .= '<div style="min-width:140px">'
                .'<dt style="opacity:.55;font-size:.75em;text-transform:uppercase;letter-spacing:.04em">'.e((string) $label).'</dt>'
                .'<dd style="margin:2px 0 0;font-weight:500">'.e((string) $value).'</dd>'
                .'</div>';
        }

        return '<dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:0">'
            .$cells.'</dl>';
    }
}
