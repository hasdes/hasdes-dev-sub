<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FruitsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        DB::insert('insert into fruits (name, price) values (?, ?)', ['りんご', NULL]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['メロン', 700]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['みかん', 300]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['バナナ', 400]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['苺',  400]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['ビワ', 600]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['キウイフルーツ', 450]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['もも',300]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['マスクメロン',1800]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['スイカ',1100]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['梨',300]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['パイナップル',500]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['ぶどう',500]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['マンゴー',700]);
        DB::insert('insert into fruits (name, price) values (?, ?)', ['パッションフルーツ',300]);
    }
}
