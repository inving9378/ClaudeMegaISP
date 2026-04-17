<?php

namespace App\Repositories;

use App\Models\Card;
use App\Models\Point;
use App\Models\Table;
use App\Models\TransactionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

abstract class BaseRepository
{
    protected $user;
    abstract public function getModel();

    public function getUser()
    {
        $user = auth()->user();
        if (!empty($user))
            return $user;

        return User::where('email', config('app.mail_system'))->first();
    }

    public function find($id)
    {
        return $this->getModel()->find($id);
    }

    public function getAll()
    {
        return $this->getModel()->all();
    }

    public function getByColumns($columnsData)
    {
        $query = $this->getModel()->query();
        foreach ($columnsData as $key => $value) {
            $query->where($key, $value);
        }
        return $query->get();
    }

    public function getByName(string $name)
    {
        return $this->getModel()->where('name', $name)->first();
    }

    public function create($data)
    {
        $user = $this->getUser();
        $json =   json_encode($data, JSON_UNESCAPED_UNICODE);
        if ((!isset($data["created_by"])) && in_array("created_by", $this->getModel()->getFillable())) {
            $data["created_by"] = $user->id;
        }

        if ((!isset($data["updated_by"])) && in_array("updated_by", $this->getModel()->getFillable())) {
            $data["updated_by"] = $user->id;
        }

        $modelo = $this->getModel()->create($data);
        TransactionLog::create([
            'model' => get_class($this->getModel()),
            'action' => "INSERT",
            'user_id' => $user->id,
            'json' => $json,
        ]);
        return $modelo;
    }

    public function update($object, $data)
    {
        $user = $this->getUser();
        if (!isset($data["updated_by"])) {
            if (!empty($user)) {
                $data["updated_by"] = $user->id;
            } else {
                $data["updated_by"] = 1;
            }
        }
        $object->fill($data);
        TransactionLog::create([
            'model' => get_class($this->getModel()),
            'action' => "UPDATE",
            'user_id' => $user != null ? $user->id : null,
            'json' => json_encode($object->toArray(), JSON_UNESCAPED_UNICODE),
        ]);
        $object->save();
        return $object;
    }

    public function delete($object)
    {
        $user = $this->getUser();
        $json =   json_encode($object, JSON_UNESCAPED_UNICODE);
        TransactionLog::create([
            'model' => get_class($this->getModel()),
            'action' => "DELETE",
            'user_id' => $user->id,
            'json' => $json,
        ]);
        $object->delete();
    }

    public function getDataEnum(string $column)
    {
        $database = config('database.connections.mysql.database');
        $model = $this->getModel();
        $table = $model->getTable();
        $columns = $model->getFillable();

        if (!in_array($column, $columns))
            return [];

        $query = DB::select("
            SELECT
                TRIM(TRAILING ')' FROM TRIM(LEADING '(' FROM TRIM(LEADING 'enum' FROM column_type))) column_type
            FROM
                information_schema.COLUMNS
            WHERE
                table_schema = '$database'
                AND TABLE_NAME = '$table'
                AND column_name = '$column'
        ");

        return explode(",", str_replace("'", "", $query[0]->column_type));
    }

    public function SearchForSelect(?string $text, array $ids, int $page, $fun = null)
    {
        $searchColumnName = $this->getSearchColumnName();
        $table = $this->getModel()->getTable();

        $querry =  DB::table($table)
            ->select(
                $table . ".id",
                "$table.$searchColumnName AS text"
            )
            ->whereNotIn($table . ".id", $ids)
            ->orderBy($table . '.' . $searchColumnName);

        if (!empty($text)) {
            $querry = $querry->where($table . '.' . $searchColumnName, 'LIKE', "%$text%");
        }

        if (!empty($fun)) {
            $fun($querry);
        }

        return $querry->paginate(7, $page);
    }

    public function getSearchColumnName()
    {
        $table = Table::where('repository_class', $this::class)->first();
        return $table->search_column_name;
    }

    public function getCaseForColumnSearch($tables, $prefix)
    {
        $string = "CASE ";

        foreach ($tables as $table) {
            $class = str_replace('\\', '\\\\', $table->model_class);
            if ($table->model_class === Point::class) {
                $string = $string . "WHEN (" . $prefix . "_ports.portable_type= '$class') THEN CONCAT('Punto', ' (', " . $prefix . "_$table->name.$table->search_column_name, ')') ";
                continue;
            }

            $string = $string . "WHEN (" . $prefix . "_ports.portable_type= '$class') THEN " . $prefix . "_$table->name.$table->search_column_name ";
        }

        return $string . "END AS " . $prefix . "put";
    }

    public function getCaseForColumnSearchMapLink($tables)
    {
        $string = "CASE ";

        foreach ($tables as $table) {
            if ($table->model_class === Point::class) {
                $string = $string . "WHEN ($table->name.id is not null) THEN CONCAT('Punto', ' (', $table->name.$table->search_column_name, ')') ";
                continue;
            }

            $string = $string . "WHEN ($table->name.id is not null) THEN $table->name.$table->search_column_name ";
        }

        return $string . " END AS name";
    }

    public function getEmpty(): Collection
    {
        return $this->getModel()->where('id', 0)->get();
    }
}
