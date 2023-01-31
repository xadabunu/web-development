<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= $tricount->title ?> &#11208; Edit template</title>
    <base href="<?= $web_root ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="css/styles.css" rel="stylesheet" type="text/css">
</head>

<body>
    <div class="main">
        <header class="t2">
            <a href="templates/manage_templates/<?= $tricount->id ?>" class="button" id="back">Back</a>
            <p><?= $tricount->title ?> &#11208; Edit template</p>
            <button form="edittemplateform" type="submit" class="button save" id="save">Save</button>
        </header>

        <form id="edittemplateform" action="templates/edit_template/<?= $tricount->id ?>/<?= $template->id ?>" method="post" class="edit">
            <label for="title">Title :</label>
            <input id="title" name="title" type="text" size="16" value="<?= $template->title ?>" <?php if (array_key_exists('empty_title', $errors) || array_key_exists('lenght', $errors)) { ?>class="errorInput" <?php } ?>>

            <?php if (array_key_exists('empty_title', $errors)) { ?>
                <p class="errorMessage"><?php echo $errors['empty_title']; ?></p>
            <?php }
            if (array_key_exists('template_lenght', $errors)) { ?>
                <p class="errorMessage"><?php echo $errors['lenght']; ?></p>
            <?php } ?>

            <label>Template items :</label>
            <ul>
                <?php foreach ($userAndWeightArray as $nom => $idAndWeightArray) { ?>
                    <li>
                        <table class="whom">
                            <tr class="edit">
                                <td class="check">
                                    <p><input type='checkbox' name='<?= $idAndWeightArray[0] ?>' value='' checked></p>
                                </td>
                                <td class="user">
                                    <?= $nom ?>
                                </td>
                                <td class="weight">
                                    <p>Weight</p><input type='text' name='weight_<?= $idAndWeightArray[0] ?>' value='<?= $idAndWeightArray[1] ?>'>
                                </td>
                            </tr>
                        </table>
                    </li>
                <?php } ?>
            </ul>

            <?php if (array_key_exists('whom', $errors)) { ?>
                <p class="errorMessage"><?php echo $errors['whom']; ?></p>
            <?php } ?>
        </form>
        <a href="templates/delete_template/<?= $template->id ?>/<?= $tricount->id ?>" class="button bottom2 delete">Delete this template</a>
    </div>
</body>

</html>