<?php

declare(strict_types=1);

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Filament\Admin\Resources\SupportTicketResource\Pages\EditSupportTicket;
use App\Filament\Admin\Resources\SupportTicketResource\Pages\ListSupportTickets;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    actingAs($this->admin);
});

function makeTicket(array $overrides = []): SupportTicket
{
    return SupportTicket::create(array_merge([
        'subject' => 'Cannot log in',
        'body' => 'I keep getting an error.',
        'category' => SupportTicketCategory::cases()[0]->value,
        'status' => SupportTicketStatus::OPEN->value,
        'priority' => SupportTicketPriority::NORMAL->value,
    ], $overrides));
}

it('assigns a ticket to the acting admin and moves an open ticket to in_progress', function (): void {
    $ticket = makeTicket(['status' => SupportTicketStatus::OPEN->value]);

    Livewire::test(ListSupportTickets::class)
        ->callTableAction('assign_to_me', $ticket);

    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($this->admin->id);
    expect($ticket->status)->toBe(SupportTicketStatus::IN_PROGRESS);
});

it('leaves a non-open ticket status untouched on assign_to_me', function (): void {
    $ticket = makeTicket(['status' => SupportTicketStatus::WAITING_USER->value]);

    // The list defaults to a status=OPEN filter; clear it so the non-open
    // ticket is resolvable by the row action.
    Livewire::test(ListSupportTickets::class)
        ->removeTableFilters()
        ->callTableAction('assign_to_me', $ticket);

    $ticket->refresh();
    expect($ticket->assigned_to)->toBe($this->admin->id);
    expect($ticket->status)->toBe(SupportTicketStatus::WAITING_USER);
});

it('edits a ticket (status / priority / assignment)', function (): void {
    $ticket = makeTicket();
    $agent = User::factory()->create();

    Livewire::test(EditSupportTicket::class, ['record' => $ticket->getKey()])
        ->fillForm([
            'subject' => 'Updated subject',
            'category' => $ticket->category->value,
            'priority' => SupportTicketPriority::URGENT->value,
            'status' => SupportTicketStatus::RESOLVED->value,
            'assigned_to' => $agent->getKey(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $ticket->refresh();
    expect($ticket->subject)->toBe('Updated subject');
    expect($ticket->priority)->toBe(SupportTicketPriority::URGENT);
    expect($ticket->status)->toBe(SupportTicketStatus::RESOLVED);
    expect($ticket->assigned_to)->toBe($agent->id);
});
