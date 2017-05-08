<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\BrowserKitTestCase;

class MshipStateTest extends BrowserKitTestCase
{
    use DatabaseTransactions;

    private $account;

    public function setUp()
    {
        parent::setUp();

        $this->account = factory(\App\Models\Mship\Account::class)->create([
            "id" => 123456,
            "name_first" => "John",
            "name_last" => "Doe",
            "email" => "i_sleep@gmail.com",
        ]);
    }

    /** @test */
    public function itAddsAState()
    {
        $divisionState = \App\Models\Mship\State::findByCode("DIVISION");

        $this->account->fresh()->addState($divisionState, "EUR", "GBR");

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $divisionState->id,
            "end_at" => null,
        ]);
    }

    /** @test */
    public function itThrowsInvalidExceptionWhenSearchingForInvalidState()
    {
        $this->setExpectedException(\App\Exceptions\Mship\InvalidStateException::class);

        $this->account->fresh()->hasState($this->account);
    }

    /** @test */
    public function itDeletesOldPermanentState()
    {
        $divisionState = \App\Models\Mship\State::findByCode("DIVISION");
        $regionState = \App\Models\Mship\State::findByCode("REGION");

        $this->account->fresh()->addState($regionState, "EUR", "EUD");

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $regionState->id,
            "end_at" => null,
        ]);

        $this->account->fresh()->fresh()->addState($divisionState, "EUR", "GBR");

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $divisionState->id,
            "end_at" => null,
        ]);

        $this->notseeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $regionState->id,
            "end_at" => null,
        ]);

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $regionState->id
        ]);
    }

    /** @test */
    public function itKeepsCurrentTemporaryState()
    {
        $visitorState = \App\Models\Mship\State::findByCode("VISITING");
        $regionState = \App\Models\Mship\State::findByCode("REGION");

        $this->account->fresh()->addState($visitorState);

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $visitorState->id,
            "end_at" => null,
        ]);

        $this->account->fresh()->fresh()->addState($regionState, "EUR", "EUD");

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $visitorState->id,
            "end_at" => null,
        ]);

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $visitorState->id,
            "end_at" => null,
        ]);
    }

    /** @test */
    public function itDeletesTemporaryStatesWhenDeleteAllTempsStateIsAdded()
    {
        $visitorState = \App\Models\Mship\State::findByCode("VISITING");
        $divisionState = \App\Models\Mship\State::findByCode("DIVISION");

        $this->account->fresh()->addState($visitorState);

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $visitorState->id,
            "end_at" => null,
        ]);

        $this->account->fresh()->addState($divisionState, "EUR", "GBR");

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $divisionState->id,
            "end_at" => null,
        ]);

        $this->notseeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $visitorState->id,
            "end_at" => null,
        ]);

        $this->seeInDatabase("mship_account_state", [
            "account_id" => $this->account->id,
            "state_id" => $visitorState->id,
        ]);
    }

    /** @test */
    public function itReturnsCorrectPrimaryStateWhenOnlyOneExists()
    {
        $divisionState = \App\Models\Mship\State::findByCode("DIVISION");

        $this->account->fresh()->addState($divisionState, "EUR", "GBR");

        $this->assertEquals($divisionState->id, $this->account->fresh()->primary_state->id);
    }

    /** @test */
    public function itReturnsCorrectPrimaryStateWhenTemporaryOverrides()
    {
        $regionState = \App\Models\Mship\State::findByCode("REGION");
        $visitorState = \App\Models\Mship\State::findByCode("VISITING");

        $this->account->fresh()->addState($regionState, "EUR", "EUD");
        $this->account->fresh()->addState($visitorState);

        $this->assertEquals($visitorState->id, $this->account->fresh()->primary_state->id);
    }

    /** @test */
    public function itReturnsCorrectPrimaryStateWhenTemporaryOverridesAndMultipleTemporary()
    {
        $regionState = \App\Models\Mship\State::findByCode("REGION");
        $visitorState = \App\Models\Mship\State::findByCode("VISITING");
        $transferringState = \App\Models\Mship\State::findByCode("TRANSFERRING");

        $this->account->fresh()->addState($regionState, "EUR", "EUD");
        $this->account->fresh()->addState($visitorState);
        $this->account->fresh()->addState($transferringState);

        $this->assertEquals($transferringState->id, $this->account->fresh()->primary_state->id);
    }

    /** @test */
    public function itThrowsDuplicateExceptionWhenAddingDuplicateState()
    {
        $this->setExpectedException(\App\Exceptions\Mship\DuplicateStateException::class);

        $regionState = \App\Models\Mship\State::findByCode("REGION");

        $this->account->fresh()->addState($regionState, "EUR", "EUD");
        $this->account->fresh()->addState($regionState, "EUR", "EUD");
    }

    /** @test */
    public function itRemainsIdempotentWhenTryingToRemoveAStateThatIsntSet()
    {
        $regionState = \App\Models\Mship\State::findByCode("REGION");

        $this->account->fresh()->removeState($regionState);
    }
}