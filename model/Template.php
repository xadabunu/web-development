<?php

require_once "framework/Model.php";
require_once "model/Tricount.php";
require_once "model/Operation.php";

class Template extends Model
{

    public function __construct(public String $title, public Tricount $tricount, public ?int $id = 0)
    {
    }

// --------------------------- Get sur les Template ------------------------------------ 

    public static function get_all_template_ids() : array 
    {
        $list = (self::execute("SELECT id FROM repartition_templates", []))->fetchAll();
        $res = [];
        foreach ($list as $var)
            $res[] = $var['id'];

        return $res;
    }     

    public static function get_templates(int $id): array
    {
        $query = self::execute("SELECT * FROM repartition_templates WHERE tricount = :id", ["id" => $id]);
        $data = $query->fetchAll();
        $array = [];
        foreach ($data as $template) {
            $tricountInstance = Tricount::get_tricount_by_id($template['tricount']);
            $array[] = new Template($template['title'], $tricountInstance, $template['id']);
        }
        return $array;
    }

    public static function get_template_by_template_id(int $id): Template
    {
        $query = self::execute("SELECT * FROM repartition_templates WHERE id = :id", ["id" => $id]);
        $data = $query->fetch();
        return new Template($data['title'], Tricount::get_tricount_by_id($data['tricount']), $data['id']);
    }

    public function get_repartition_items(): array
    {
        $array = [];
        $query = self::execute("SELECT * FROM repartition_template_items WHERE repartition_template = :id", ["id" => $this->id]);
        $data = $query->fetchAll();
        foreach ($data as $template_item) {
            $array[$template_item["user"]] =  $template_item["weight"];
        }
        return $array;
    }

    public function get_template_user_and_weight(): array
    {
        $query = self::execute("SELECT user, weight FROM repartition_template_items WHERE repartition_template = :id", ["id" => $this->id]);
        $data = $query->fetchAll();
        $list = [];
        foreach ($data as $var) {
            $list[$var['user']] = $var['weight'];
        }
        return $list;
    }

    public function get_template_users(): array
    {
        $query = self::execute("SELECT user FROM repartition_template_items WHERE repartition_template = :id", ["id" => $this->id]);
        $data = $query->fetchAll();
        $list = [];
        foreach ($data as $var) {
            $list[] = $var['user'];
        }
        return $list;
    }

    public static function get_template_by_title(string $title): Template | false
    {
        $query = self::execute("SELECT * FROM repartition_templates WHERE title = :title", ["title" => $title]);
        $data = $query->fetch();
        return new Template($data['title'], Tricount::get_tricount_by_id($data['tricount']), $data['id']);
    }

// --------------------------- Validate && Persist ------------------------------------ 


    public function validate_template(): array
    {
        $errors = [];
        if (strlen($this->title) < 3 || strlen($this->title) > 256) {
            $errors['template_length'] = "Title length must be between 3 and 256.";
        }
        $template = self::get_template_by_title($this->title);
        if($template){
            $errors['duplicate_title'] = "Title already exists in this tricount.";
        }
        return $errors;
    }

    public function persist_template(): Template
    {
        if ($this->id != 0) {
            self::execute(
                "UPDATE repartition_templates SET title= :title, tricount= :tricount WHERE id= :id",
                ["title" => $this->title, 'tricount' => $this->tricount->id, 'id' => $this->id]
            );
        } else {
            self::execute(
                "INSERT INTO repartition_templates(title, tricount) VALUES(:title, :tricount)",
                ["title" => $this->title, 'tricount' => $this->tricount->id]
            );
        }
        $this->id = Model::lastInsertId();
        return $this;
    }

    public function persist_template_items(Template $template, array $list): void
    {
        $this->delete_template_items();
        $array = array_keys($list);
        foreach ($array as $id) {
            self::execute("INSERT INTO repartition_template_items(user, repartition_template, weight) VALUES(:user, :repartition_template, :weight)", ['user' => $id, 'repartition_template' => $template->id, 'weight' => $list[$id]]);
        }
    }

    public function add_template_from_operation(array $list, Template $template): void
    {
        $template->persist_template();
        $template->persist_template_items($template, $list);
    }


// --------------------------- Delete Template ------------------------------------ 


    public function delete_template_items(): void
    {
        self::execute("DELETE FROM repartition_template_items WHERE repartition_template= :id", ["id" => $this->id]);
    }

    public function delete_template(): void
    {
        self::execute("DELETE FROM repartition_templates WHERE id= :id", ["id" => $this->id]);
    }

}