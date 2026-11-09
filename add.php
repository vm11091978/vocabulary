<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$vocabulary = getVocabulary();

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $word = trim($_POST['word'] ?? '');
    $translation = trim($_POST['translation'] ?? '');
    $weight = (int)($_POST['weight'] ?? 2);

    if ($word === '' || $translation === '') {

        $error = 'Заполни английское слово и перевод.';

    } elseif (!in_array($weight, [1, 2, 3], true)) {

        $error = 'Вес должен быть 1, 2 или 3.';

    } else {

        // Проверяем, нет ли уже такого слова.
        $exists = false;

        foreach ($vocabulary as $existing) {

            if (
                mb_strtolower($existing['word'])
                === mb_strtolower($word)
            ) {
                $exists = true;
                break;
            }
        }

        if ($exists) {

            $error = 'Такое слово уже есть в словаре.';

        } else {

            $maxId = 0;

            foreach ($vocabulary as $existing) {
                $maxId = max(
                    $maxId,
                    (int)$existing['id']
                );
            }

            $translations = array_map(
                'trim',
                explode(',', $translation)
            );

            $vocabulary[] = [
                'id' => $maxId + 1,
                'word' => $word,
                'translations' => $translations,
                'weight' => $weight
            ];

            writeJson(
                VOCABULARY_FILE,
                $vocabulary
            );

            $message = 'Слово добавлено.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add word</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="container">

    <div class="top-nav">
        <a href="index.php">
            Тест «Написать перевод»
        </a>

        <a href="multiple_choice.php">
            Тест «Выбрать перевод»
        </a>

        <a href="stats.php">
            Статистика
        </a>

        <a href="history.php">
            История
        </a>

        <a href="choice_history.php">
            История выбора
        </a>

        <a href="add.php">
            Добавить слово
        </a>
    </div>


    <div class="card">

        <h1>Добавить слово</h1>


        <?php if ($message): ?>

            <div class="message success">
                <?= e($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="message error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <form method="post">

            <label>
                Английское слово
            </label>

            <input
                type="text"
                name="word"
                autofocus
                required
            >


            <label>
                Перевод
            </label>

            <input
                type="text"
                name="translation"
                placeholder="например: необходимый, нужный"
                required
            >


            <label>
                Вес
            </label>

            <select name="weight">

                <option value="3">
                    3 — очень частое
                </option>

                <option value="2" selected>
                    2 — среднее
                </option>

                <option value="1">
                    1 — редкое
                </option>

            </select>


            <button type="submit">
                Добавить
            </button>

        </form>

    </div>

</div>

</body>
</html>
