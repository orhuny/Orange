<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Slot themes
    |--------------------------------------------------------------------------
    |
    | A theme is a slot's look and sound: its art in public/images/{theme}/ and its
    | view data in resources/views/games/slot/themes/{theme}/theme.php. It draws one
    | maths model's symbols (games.config.model), so a theme names the model it fits.
    | games.config.theme picks one; see docs/adding-a-game.md, "Adding a slot".
    |
    */

    'themes' => [
        'sugar-orchard' => ['label' => 'Sugar Orchard', 'model' => 'sugar-orchard'],
    ],
];
