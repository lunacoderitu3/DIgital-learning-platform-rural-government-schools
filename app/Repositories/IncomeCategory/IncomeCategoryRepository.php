<?php

namespace App\Repositories\IncomeCategory;

use App\Models\IncomeCategory;
use App\Repositories\Saas\SaaSRepository;

class IncomeCategoryRepository extends SaaSRepository implements IncomeCategoryInterface {

    public function __construct(IncomeCategory $model) {
        parent::__construct($model);
    }
}
