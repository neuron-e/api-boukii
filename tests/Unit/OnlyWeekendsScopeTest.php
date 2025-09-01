<?php

namespace Tests\Unit;

use App\Models\BookingUser;
use Illuminate\Http\Request;
use Tests\TestCase;

class OnlyWeekendsScopeTest extends TestCase
{
    public function test_only_weekends_parameter_applies_scope()
    {
        // use in-memory sqlite connection
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);

        $request = new Request(['only_weekends' => 'true']);
        $onlyWeekends = $request->boolean('only_weekends', $request->boolean('onlyWeekends', false));

        $query = BookingUser::query()->when($onlyWeekends, fn($q) => $q->onlyWeekends());

        $this->assertStringContainsString('WEEKDAY(date) IN (5, 6)', $query->toSql());
    }
}
