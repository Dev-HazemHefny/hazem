<?php

namespace App\Services\Accounting;

use App\Models\Account;
use Illuminate\Support\Collection;

class AccountQueryService
{
    public function list(): Collection
    {
        return Account::orderBy('code')->get();
    }
}
