<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= $web_root ?>">
    <link href="css/styles.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <title>Your Tricounts</title>
</head>

<body>
    <div class="main">
        <header class="t2">
            <p></p>
            <p>Your Tricounts</p>
            <a href="tricount/add_tricount" class="button" id="add">Add</a>
        </header>
        <?php if (empty($data)) { ?>
            <table>
                <tr>
                    <th class="empty"> You have no tricount!</th>
                </tr>
                <tr>
                    <td class="empty">
                        <p>Click below to add a tricount!</p>
                        <a href="tricount/add_tricount" class="button">Add a tricount</a>
                    </td>
                </tr>
            </table>
            <?php } else { ?>
        <table>
            <?php foreach ($data as $tricount) { ?>
                <tr>
                    <td>
                        <p><b><a href="tricount/operations/<?= $tricount->id ?>"><?= $tricount->title ?></a></b></p>
                        <p><?= $tricount->description ?></p>
                    </td>
                    <td class="right">
                        <p><?= $subs_number[$tricount->id] ?></p>
                    </td>
                </tr>
            <?php } ?>
        </table>
        <?php } ?>
        <a href="settings/my_settings" class="bottomrightcorner"><i class="fa fa-cog fa-2x" aria-hidden="true" style="color:goldenrod"></i></a>

    </div>

</body>

</html>