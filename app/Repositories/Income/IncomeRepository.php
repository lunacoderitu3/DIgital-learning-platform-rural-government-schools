<?php

namespace App\Repositories\Income;

use App\Models\Income;
use App\Repositories\Saas\SaaSRepository;

class IncomeRepository extends SaaSRepository implements IncomeInterface {

    public function __construct(Income $model) {
        parent::__construct($model, 'income');
    }
}
