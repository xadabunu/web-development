<?php

require_once "framework/Model.php";
require_once "model/Tricount.php";
require_once "model/User.php";

class Operation extends Model
{
    public function __construct(public string $title, public Tricount $tricount, public float $amount, public string $operation_date, public User $initiator, public string $created_at, public ?int $id = 0)
    {
    }

// --------------------------- Get sur les operations ------------------------------------ 


    public static function get_operation_by_id(int $id): Operation
    {
        $query = self::execute("SELECT * FROM operations WHERE id = :id", ["id" => $id]);
        $data = $query->fetch();
        return new Operation($data['title'], Tricount::get_tricount_by_id($data['tricount']), $data['amount'], $data['operation_date'], User::get_user_by_id($data['initiator']), $data['created_at'], $data['id']);
    }

    public function get_previous(): int | null
    {
        $query = self::execute("WITH tmp AS (SELECT * FROM operations WHERE tricount = :tricount_id ORDER BY created_at)
                        select prev_id
                        from ( select id, 
                            lag(id) over (ORDER BY created_at) as prev_id
                            from tmp) as t where id = :op_id", [
            "tricount_id" => $this->tricount->id,
            "op_id" => $this->id
        ]);
        return ($query->fetch())['prev_id'];
    }

    public function get_next(): int | null
    {
        $query = self::execute("WITH tmp AS (SELECT * FROM operations WHERE tricount = :tricount_id ORDER BY created_at)
                        select next_id
                        from ( select id, 
                            lead(id) over (ORDER BY created_at) as next_id
                            from tmp) as t where id = :op_id", [
            "tricount_id" => $this->tricount->id,
            "op_id" => $this->id
        ]);
        return ($query->fetch())['next_id'];
    }

    public static function get_all_operations_id(): array
    {
        $list = (self::execute("SELECT id FROM operations", []))->fetchAll();
        $res = [];
        foreach ($list as $var)
            $res[] = $var['id'];

        return $res;
    }


// --------------------------- Méthode has_access --------------------------------


    public function has_access(User $user): bool
    {
        $list = $this->get_participants();
        return in_array($user, $list);
    }


// --------------------------- Validate && Persist // Delete && delete Cascade des operations ------------------------------------ 


    public function validate_operations(): array
    {
        $errors = [];
        if (!(strlen($this->title) >= 3)) {
            $errors['length'] = "Title length must be higher than 3.";
        }
        elseif (strlen($this->title) > 256) {
            $errors['length'] = "Title can't be longer than 256 characters.";
        }
        if (($this->amount) <= 0) {
            $errors['amount'] = "Amount must be positive";
        }
        return $errors;
    }

    public function persist_operation(): Operation
    {
        if ($this->id != 0) {
            self::execute(
                "UPDATE operations SET title= :title, tricount= :tricount, amount= :amount, operation_date= :operation_date, initiator= :initiator, created_at= :created_at WHERE id= :id",
                [
                    "title" => $this->title, 'tricount' => $this->tricount->id, 'amount' => $this->amount, 'operation_date' => $this->operation_date,
                    'initiator' => $this->initiator->id, 'created_at' => $this->created_at, 'id' => $this->id
                ]
            );
        } else {
            self::execute(
                "INSERT INTO operations(title, tricount, amount, operation_date, initiator, created_at) VALUES(:title, :tricount, :amount, :operation_date, :initiator, :created_at)",
                ["title" => $this->title, 'tricount' => $this->tricount->id, 'amount' => $this->amount, 'operation_date' => $this->operation_date, 'initiator' => $this->initiator->id, 'created_at' => $this->created_at]
            );
        }

        $this->id = Model::lastInsertId();
        return $this;
    }

    public function delete_operation_cascade(): void
    {
        $this->delete_repartition();
        $this->delete_operation();
    }

    private function delete_operation(): void
    {
        self::execute("DELETE FROM operations WHERE id= :id ", ["id" => $this->id]);
    }

    private function delete_repartition(): void
    {
        self::execute("DELETE FROM repartitions WHERE operation= :id", ["id" => $this->id]);
    }


// --------------------------- Get sur participants && repartition // Persist repartition ------------------------------------ 


    public function get_participants(): array
    {
        $query = self::execute("SELECT u.* FROM users u join repartitions r on u.id = r.user WHERE r.operation = :id", ["id" => $this->id]);
        $data = $query->fetchAll();
        $array = [];
        foreach ($data as $user) {
            $array[] = new User($user['mail'], $user['hashed_password'], $user['full_name'], $user['role'], $user['iban'], $user['id']);
        }
        return $array;
    }

    public function get_repartitions(): array
    {
        $query = self::execute("SELECT * FROM repartitions WHERE operation = :id", ["id" => $this->id]);
        $data = $query->fetchAll();
        $list = [];
        foreach ($data as $var) {
            $list[$var['user']] = $var['weight'];
        }
        return $list;
    }

    public function get_user_amount(int $user_id): float
    {
        $query = self::execute("SELECT SUM(weight) as sum FROM repartitions WHERE operation = :id", ["id" => $this->id]);
        $sum = ($query->fetch())['sum'];

        $query = self::execute("SELECT weight FROM repartitions WHERE user = :user_id AND operation = :id", ["user_id" => $user_id, "id" => $this->id]);

        $data = $query->fetch();

        if (gettype($data) != "array") {
            return 0;
        }
        $weight = ($data['weight']);

        return ($this->amount) / $sum * $weight;
    }

    public function persist_repartition(Operation $operation, array $list): void
    {
        $this->delete_repartition();
        $array = array_keys($list);
        foreach ($array as $id) {
            self::execute("INSERT INTO repartitions(operation, user, weight) VALUES(:operation, :user, :weight)", ['operation' => $operation->id, 'user' => $id, 'weight' => $list[$id]]);
        }
    }
}