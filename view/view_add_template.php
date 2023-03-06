<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= strlen($tricount->title) > 20 ? substr($tricount->title, 0, 20)."..." : $tricount->title ?> &#11208; New template</title>
    <base href="<?= $web_root ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="css/styles.css" rel="stylesheet" type="text/css">
</head>

<body>
    <div class="main">
        <header class="t2">
            <a href="templates/manage_templates/<?= $tricount->id ?>" class="button" id="back">Back</a>
            <p><?php echo strlen($tricount->title) <= 20 ? $tricount->title : substr($tricount->title, 0, 17)."..."?> &#11208; New template</p>
            <button form="addtemplateform" type="submit" class="button save" id="save">Save</button>
        </header>

        <form id="addtemplateform" action="templates/add_template/<?= $tricount->id ?>" method="post" class="edit">
            <label for="title">Title :</label>
            <input id="title" name="title" type="text" size="16" <?php if (array_key_exists('empty_title', $errors) || array_key_exists('length', $errors)) { ?>class="errorInput" <?php } ?>>

            <?php if (array_key_exists('empty_title', $errors)) { ?>
                <p class="errorMessage"><?php echo $errors['empty_title']; ?></p>
            <?php }
            if (array_key_exists('template_length', $errors)) { ?>
                <p class="errorMessage"><?php echo $errors['template_length']; ?></p>
            <?php } ?>
            <label>Template items :</label>
            <?php if (count($errors) != 0) { ?>
                <ul>
                    <?php foreach ($subscriptors as $subscriptor) { ?> <!-- Cas si il y'a des erreurs et que la page s'est donc réaffichée  --> 
                        <li>
                            <table class="whom">
                                <tr class="edit">
                                    <td class="check">
                                        <p><input type='checkbox' <?php if (array_key_exists($subscriptor->id, $list)) echo "checked" ?> name='<?= $subscriptor->id ?>' value=''></p>
                                    </td>
                                    <td class="user">
                                    <?= strlen($subscriptor->full_name) > 25 ? substr($subscriptor->full_name, 0, 25)."..." : $subscriptor->full_name ?>
                                    </td>
                                    <td class="weight">
                                        <p>Weight</p><input type='text' name='weight_<?= $subscriptor->id ?>' value='<?php if (array_key_exists($subscriptor->id, $list)) echo $list[$subscriptor->id]; else{ echo 1;} ?>'>
                                    </td>
                                </tr>
                            </table>
                        </li>
                    <?php } ?>
                </ul>
            <?php }  else { ?>
                <ul>
                    <?php foreach ($subscriptors as $subscriptor) { ?> <!-- Cas par défaut on affiche tout le monde checked avec un poids à 1 -->
                        <li>
                            <table class="whom">
                                <tr class="edit">
                                    <td class="check">
                                        <p><input type='checkbox' name='<?= $subscriptor->id ?>' value='' checked></p>
                                    </td>
                                    <td class="user">
                                    <?= strlen($subscriptor->full_name) > 25 ? substr($subscriptor->full_name, 0, 25)."..." : $subscriptor->full_name ?>
                                    </td>
                                    <td class="weight">
                                        <p>Weight</p><input type='text' name='weight_<?= $subscriptor->id ?>' value='1'>
                                    </td>
                                </tr>
                            </table>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
            <?php if (array_key_exists('whom', $errors)) { ?>
                <p class="errorMessage"><?php echo $errors['whom']; ?></p>
            <?php } ?>
            <?php if (array_key_exists('weight', $errors)) { ?>
                <p class="errorMessage"><?php echo $errors['weight']; ?></p>
            <?php } ?>

        </form>

    </div>
</body>

</html>