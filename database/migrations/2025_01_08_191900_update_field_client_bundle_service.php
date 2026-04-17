<?php

use App\Models\FieldModule;
use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //TODO SE van a modificar estos fields
        /*
        [
    0 => 3
    1 => 5
    2 => 7
    3 => 22
    4 => 27
    5 => 29
    6 => 32
    7 => 42
    8 => 49
    9 => 51
    10 => 53
    11 => 60
    12 => 68
    13 => 75
    14 => 77
    15 => 78
    16 => 86
    17 => 93
    18 => 95
    19 => 96
    20 => 108
    21 => 119
    22 => 147
    23 => 232
    24 => 302
    25 => 319
    26 => 361
    27 => 362
    28 => 363
    29 => 371
    30 => 372
    31 => 373
    32 => 378
    33 => 411
    34 => 432
    35 => 436
    36 => 439
    37 => 444
    38 => 445
    39 => 449
    40 => 457
    41 => 475
    42 => 494
    43 => 495
    44 => 496
    45 => 497
    46 => 498
    47 => 503
    48 => 508
    49 => 513
    50 => 518
    51 => 519
    52 => 524
    53 => 527
    54 => 533
    55 => 534
    56 => 539
    57 => 542
    58 => 544
    59 => 549
    60 => 554
    61 => 559
    62 => 561
    63 => 566
    64 => 571
    65 => 584
    66 => 620
    67 => 651
    68 => 690
    69 => 698
    70 => 699
    71 => 869
    72 => 870
    73 => 871
    74 => 872
    75 => 873
    76 => 874
    77 => 875
    78 => 876
    79 => 877
    80 => 878
    81 => 879
    82 => 880
    83 => 881
    84 => 886
    85 => 887
    86 => 888
    87 => 889
    88 => 890
    89 => 891
    90 => 892
    91 => 893
    92 => 940
    93 => 941
  ] */

        $fieldsModules = FieldModule::whereIn('type', [16, 17, 18, 27])->where('value', false)->get();
        foreach ($fieldsModules as $fieldModule) {
            $fieldModule->value = null;
            $fieldModule->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $fieldsModules = FieldModule::whereIn('type', [16, 17, 18, 27])->where('value', null)->get();
        foreach ($fieldsModules as $fieldModule) {
            $fieldModule->value = false;
            $fieldModule->save();
        }
    }
};
