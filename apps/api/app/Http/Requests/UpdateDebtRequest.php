<?php

namespace App\Http\Requests;

// The MVP has no partial-update screen for debts (the design only wires up
// creation) — reuse the same full-payload validation for the PATCH endpoint.
class UpdateDebtRequest extends StoreDebtRequest {}
