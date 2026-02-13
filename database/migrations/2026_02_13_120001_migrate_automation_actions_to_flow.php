<?php

use App\Models\Automation;
use App\Models\AutomationAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $automations = Automation::query()->with(['actions' => fn ($q) => $q->orderBy('position')])->get();

        foreach ($automations as $automation) {
            $actions = $automation->actions;
            if ($actions->isEmpty()) {
                continue;
            }

            $startId = DB::table('automation_nodes')->insertGetId([
                'automation_id' => $automation->id,
                'type' => 'start',
                'position_x' => 100,
                'position_y' => 100,
                'config' => null,
                'label' => __('Início'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $prevId = $startId;
            $x = 100;
            $y = 280;

            foreach ($actions as $i => $action) {
                $nodeType = $this->mapActionTypeToNodeType($action->type);
                $nodeId = DB::table('automation_nodes')->insertGetId([
                    'automation_id' => $automation->id,
                    'type' => $nodeType,
                    'position_x' => $x,
                    'position_y' => $y + $i * 120,
                    'config' => $action->config ? json_encode($action->config) : null,
                    'label' => $this->labelForType($nodeType),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('automation_edges')->insert([
                    'automation_id' => $automation->id,
                    'source_node_id' => $prevId,
                    'target_node_id' => $nodeId,
                    'source_handle' => 'default',
                    'target_handle' => 'input',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $prevId = $nodeId;
            }
        }
    }

    public function down(): void
    {
        DB::table('automation_edges')->truncate();
        DB::table('automation_nodes')->truncate();
    }

    private function mapActionTypeToNodeType(string $actionType): string
    {
        return match ($actionType) {
            'send_whatsapp_message' => 'send_message',
            'wait_delay' => 'delay',
            'add_to_list' => 'add_list',
            'add_tag' => 'add_tag',
            default => $actionType,
        };
    }

    private function labelForType(string $type): string
    {
        return match ($type) {
            'send_message' => __('Mensagem'),
            'delay' => __('Aguardar'),
            'add_tag' => __('Adicionar tag'),
            'add_list' => __('Adicionar à lista'),
            default => $type,
        };
    }
};
