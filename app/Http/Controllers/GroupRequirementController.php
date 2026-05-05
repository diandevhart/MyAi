<?php

namespace App\Http\Controllers;

use App\Models\GroupRequirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class GroupRequirementController extends Controller
{
    public function index(): InertiaResponse
    {
        $rootNodes = GroupRequirement::whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->withCount(['children', 'inventoryEquipment']);
            }])
            ->withCount(['children', 'inventoryEquipment'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Catalogue/Index', [
            'rootNodes' => $rootNodes,
        ]);
    }

    public function children(int $id): JsonResponse
    {
        $parent = GroupRequirement::findOrFail($id);

        $children = $parent->children()
            ->withCount(['children', 'inventoryEquipment'])
            ->orderBy('name')
            ->get()
            ->map(function ($child) {
                return [
                    'id' => $child->id,
                    'name' => $child->name,
                    'type' => $child->type,
                    'level' => $child->level,
                    'description' => $child->description,
                    'item_count' => $child->inventory_equipment_count,
                    'has_children' => $child->children_count > 0,
                    'children_count' => $child->children_count,
                ];
            });

        return response()->json($children);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:group,item,kit,ccu,ppe',
            'parent_id' => 'nullable|exists:group_requirements,id',
            'description' => 'nullable|string',
        ]);

        $level = 0;
        $parentId = $request->input('parent_id');

        if ($request->filled('parent_id')) {
            $parent = GroupRequirement::findOrFail($parentId);

            if ($parent->type !== 'group') {
                return redirect()->back()->withErrors([
                    'parent_id' => 'Only group nodes can have children.',
                ]);
            }

            $level = $parent->level + 1;
        }

        $name = trim((string) $request->input('name'));
        $siblingDup = GroupRequirement::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where(function ($q) use ($parentId) {
                if ($parentId) {
                    $q->where('parent_id', $parentId);
                } else {
                    $q->whereNull('parent_id');
                }
            })
            ->exists();

        if ($siblingDup) {
            return redirect()->back()->withErrors([
                'name' => 'A node with this name already exists at the same level.',
            ]);
        }

        $nextSortOrder = GroupRequirement::where('parent_id', $parentId)->max('sort_order');

        GroupRequirement::create([
            'name' => $name,
            'type' => $request->input('type'),
            'parent_id' => $parentId,
            'level' => $level,
            'description' => $request->input('description'),
            'sort_order' => is_null($nextSortOrder) ? 0 : $nextSortOrder + 1,
            'is_active' => true,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, int $id)
    {
        $node = GroupRequirement::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:group,item,kit,ccu,ppe',
            'description' => 'nullable|string',
        ]);

        // Prevent type change if node has children or linked equipment
        if ($request->input('type') !== $node->type) {
            $hasChildren = $node->children()->exists();
            $hasEquipment = $node->inventoryEquipment()->exists();

            if ($hasChildren || $hasEquipment) {
                return redirect()->back()->withErrors([
                    'type' => 'Cannot change type when node has children or linked equipment.',
                ]);
            }
        }

        $name = trim((string) $request->input('name'));
        $siblingDup = GroupRequirement::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $node->id)
            ->where(function ($q) use ($node) {
                if ($node->parent_id) {
                    $q->where('parent_id', $node->parent_id);
                } else {
                    $q->whereNull('parent_id');
                }
            })
            ->exists();

        if ($siblingDup) {
            return redirect()->back()->withErrors([
                'name' => 'A node with this name already exists at the same level.',
            ]);
        }

        $node->update([
            'name' => $name,
            'type' => $request->input('type'),
            'description' => $request->input('description'),
        ]);

        return redirect()->back();
    }

    public function destroy(int $id)
    {
        $node = GroupRequirement::findOrFail($id);

        if ($node->children()->exists()) {
            return redirect()->back()->withErrors([
                'delete' => 'Cannot delete a node that has children. Remove children first.',
            ]);
        }

        if ($node->inventoryEquipment()->exists()) {
            return redirect()->back()->withErrors([
                'delete' => 'Cannot delete a node with linked equipment. Remove equipment first.',
            ]);
        }

        $node->delete();

        return redirect()->back();
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1',
        ]);

        $term = '%' . mb_strtolower($request->input('q')) . '%';

        $results = GroupRequirement::whereRaw('LOWER(name) LIKE ?', [$term])
            ->orWhereRaw('LOWER(description) LIKE ?', [$term])
            ->limit(50)
            ->get()
            ->map(function ($node) {
                return [
                    'id' => $node->id,
                    'name' => $node->name,
                    'type' => $node->type,
                    'level' => $node->level,
                    'description' => $node->description,
                    'breadcrumb' => $this->buildBreadcrumb($node),
                ];
            });

        return response()->json($results);
    }

    protected function buildBreadcrumb(GroupRequirement $node): string
    {
        $parts = [$node->name];
        $current = $node;

        while ($current->parent_id) {
            $current = GroupRequirement::find($current->parent_id);
            if (!$current) {
                break;
            }
            array_unshift($parts, $current->name);
        }

        return implode(' > ', $parts);
    }
}
