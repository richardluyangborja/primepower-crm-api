<?php

use App\Enums\CommunicationDirection;
use App\Enums\CommunicationOutcome;
use App\Enums\CommunicationType;
use App\Enums\UserRole;
use App\Models\Communication;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeCompanyAndContact(): array
{
    $company = Company::create([
        'name' => 'Acme Co '.uniqid(),
        'industry' => 'Tech',
        'address' => '123 Test St',
        'phone' => '+1 555 0100',
        'email' => 'acme+'.uniqid().'@example.com',
        'website' => null,
    ]);

    $contact = Contact::create([
        'company_id' => $company->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'title' => 'Director',
        'email' => 'jane+'.uniqid().'@example.com',
        'phone' => '+1 555 0101',
        'is_primary' => true,
    ]);

    return [$company, $contact];
}

it('accepts an outcome when creating a communication', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-comm@example.com']);
    [$company, $contact] = makeCompanyAndContact();

    $response = $this->actingAs($rep)->postJson('/api/communications', [
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'type' => CommunicationType::PHONE->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'outcome' => CommunicationOutcome::INTERESTED->value,
        'subject' => 'Quick chat',
        'notes' => 'Wants a follow up next week',
    ]);

    $response->assertCreated();
    $comm = Communication::first();
    expect($comm->outcome)->toBe(CommunicationOutcome::INTERESTED);
    expect($comm->user_id)->toBe($rep->id);
});

it('lets the author edit within the grace period', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-grace@example.com']);
    [$company, $contact] = makeCompanyAndContact();

    $comm = Communication::create([
        'company_id' => $company->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'subject' => 'Original',
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($rep)->putJson("/api/communications/{$comm->id}", [
        'subject' => 'Updated subject',
        'notes' => 'Added context',
    ]);

    $response->assertOk();
    expect($comm->fresh()->subject)->toBe('Updated subject');
});

it('blocks the author from editing after the grace period', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-after@example.com']);
    [$company, $contact] = makeCompanyAndContact();

    $comm = new Communication([
        'company_id' => $company->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'subject' => 'Original',
    ]);
    $comm->created_at = now()->subHours(2);
    $comm->updated_at = now()->subHours(2);
    $comm->save();

    $response = $this->actingAs($rep)->putJson("/api/communications/{$comm->id}", [
        'subject' => 'Updated',
    ]);

    $response->assertForbidden();
    expect($comm->fresh()->subject)->toBe('Original');
});

it('lets a manager bypass the grace period', function () {
    $manager = User::factory()->create(['role' => UserRole::MANAGER, 'email' => 'mgr-grace@example.com']);
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-mgrbypass@example.com']);
    [$company, $contact] = makeCompanyAndContact();

    $comm = new Communication([
        'company_id' => $company->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'subject' => 'Original',
    ]);
    $comm->created_at = now()->subDays(3);
    $comm->updated_at = now()->subDays(3);
    $comm->save();

    $response = $this->actingAs($manager)->putJson("/api/communications/{$comm->id}", [
        'subject' => 'Manager fix',
    ]);

    $response->assertOk();
    expect($comm->fresh()->subject)->toBe('Manager fix');
});

it('soft deletes a communication', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-del@example.com']);
    [$company, $contact] = makeCompanyAndContact();

    $comm = Communication::create([
        'company_id' => $company->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'subject' => 'To remove',
    ]);

    $this->actingAs($rep)->deleteJson("/api/communications/{$comm->id}")->assertNoContent();

    expect(Communication::count())->toBe(0);
    expect(Communication::withTrashed()->count())->toBe(1);
});

it('filters communications by type and search', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-filter@example.com']);
    [$company, $contact] = makeCompanyAndContact();

    Communication::create([
        'company_id' => $company->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'subject' => 'Pricing follow up',
        'notes' => 'Discussed the renewal quote',
    ]);

    Communication::create([
        'company_id' => $company->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::PHONE->value,
        'direction' => CommunicationDirection::INCOMING->value,
        'subject' => 'Voicemail',
        'notes' => 'Called back, no answer',
    ]);

    $response = $this->actingAs($rep)->getJson('/api/communications?type=phone&q=voicemail');

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['subject'])->toBe('Voicemail');
});

it('filters the sales reps own communications by outcome and direction', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-mine-filter@example.com']);
    [$company, $contact] = makeCompanyAndContact();

    Communication::create([
        'company_id' => $company->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::PHONE->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'outcome' => CommunicationOutcome::INTERESTED->value,
        'subject' => 'Call one',
    ]);

    Communication::create([
        'company_id' => $company->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::INCOMING->value,
        'outcome' => CommunicationOutcome::NOT_NOW->value,
        'subject' => 'Email one',
    ]);

    $response = $this->actingAs($rep)->getJson('/api/communications/mine?outcome='.CommunicationOutcome::INTERESTED->value);

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['subject'])->toBe('Call one');
});

it('only returns own communications for the mine endpoint', function () {
    $rep = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-mine-only@example.com']);
    $other = User::factory()->create(['role' => UserRole::SALES_REP, 'email' => 'rep-mine-other@example.com']);
    [$company, $contact] = makeCompanyAndContact();

    Communication::create([
        'company_id' => $company->id,
        'user_id' => $rep->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'subject' => 'Mine',
    ]);

    Communication::create([
        'company_id' => $company->id,
        'user_id' => $other->id,
        'type' => CommunicationType::EMAIL->value,
        'direction' => CommunicationDirection::OUTGOING->value,
        'subject' => 'Theirs',
    ]);

    $response = $this->actingAs($rep)->getJson('/api/communications/mine');

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['subject'])->toBe('Mine');
});
