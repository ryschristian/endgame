<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\TicketCatalogService;
use App\Support\TicketPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketCatalogController extends Controller
{
    public function save(Request $request, Project $project, string $catalog, TicketCatalogService $service): RedirectResponse
    {
        TicketPageData::authorize($request->user(), $project, 'configure');
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'id' => ['nullable', 'integer'], 'name' => ['required', 'string'], 'position' => ['required', 'integer'], 'is_active' => ['required', 'boolean'], 'is_default' => ['required', 'boolean']]);
        $service->save($request->user(), $project, $data['revision'], $catalog, $data['id'] ?? null, $data);

        return redirect()->route('tickets.index', $project)->with('status', 'Ticket catalog saved.');
    }
}
