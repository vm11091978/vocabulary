<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';


// =========================================================
// Загрузка данных
// =========================================================

$vocabulary = getVocabulary();
$progress = getProgress();


// =========================================================
// Основные процентные показатели
// =========================================================

$knowledge = calculateKnowledgePercent(
    $vocabulary,
    $progress
);

$spelling = calculateSpellingPercent(
    $vocabulary,
    $progress
);


// =========================================================
// Общая статистика
// =========================================================

$totalAttempts = 0;
$totalCorrect = 0;
$totalSpellingErrors = 0;
$totalUnknown = 0;

$masteredWords = 0;
$masteredWithoutErrors = 0;


// =========================================================
// Слабые слова
// =========================================================

$weakWords = [];


foreach ($vocabulary as $word) {

    $wordId = (int)$word['id'];

    $p = getWordProgress(
        $progress,
        $wordId
    );


    // -----------------------------------------------------
    // Общие показатели
    // -----------------------------------------------------

    $totalAttempts += (int)$p['attempts'];

    $totalCorrect += (int)$p['correct'];

    $totalSpellingErrors +=
        (int)$p['spelling_errors'];

    $totalUnknown +=
        (int)$p['unknown'];


    // -----------------------------------------------------
    // Освоенность
    // -----------------------------------------------------

    if (isWordMastered($p)) {
        $masteredWords++;
    }


    if (
        isWordMasteredWithoutSpellingErrors($p)
    ) {
        $masteredWithoutErrors++;
    }


    // -----------------------------------------------------
    // Слабые слова
    //
    // Показываем только слова, по которым
    // была хотя бы одна ошибка.
    //
    // score = 1 → орфографическая ошибка
    // score = 0 → слово не знаем
    // -----------------------------------------------------

    $errors =
        (int)$p['spelling_errors'] +
        (int)$p['unknown'];


    if ($errors > 0) {

        $successfulAnswers =
            (int)$p['correct'] +
            (int)$p['spelling_errors'];


        $attempts =
            (int)$p['attempts'];


        $wordKnowledge = 0;


        if ($attempts > 0) {

            $wordKnowledge =
                (
                    $successfulAnswers /
                    $attempts
                ) * 100;
        }


        $weakWords[] = [
            'word' => $word,
            'knowledge' => $wordKnowledge,
            'attempts' => $attempts,
            'errors' => $errors
        ];
    }
}


// =========================================================
// Сортировка слабых слов
//
// Сначала слова с худшим процентом знания.
// Если процент одинаковый — сначала слова
// с большим количеством попыток.
// =========================================================

usort(
    $weakWords,
    function (
        array $a,
        array $b
    ): int {

        if (
            $a['knowledge'] ===
            $b['knowledge']
        ) {

            return
                $b['attempts']
                <=>
                $a['attempts'];
        }


        return
            $a['knowledge']
            <=>
            $b['knowledge'];
    }
);


// =========================================================
// Количество освоенных слов в процентах
// =========================================================

$totalWords = count($vocabulary);

$masteredPercent = 0;
$masteredWithoutErrorsPercent = 0;


if ($totalWords > 0) {

    $masteredPercent = round(
        (
            $masteredWords /
            $totalWords
        ) * 100,
        1
    );


    $masteredWithoutErrorsPercent = round(
        (
            $masteredWithoutErrors /
            $totalWords
        ) * 100,
        1
    );
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

    <title>Статистика</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body>

<div class="container">


    <!-- =================================================
         Навигация
         ================================================= -->

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


    <!-- =================================================
         Основная карточка
         ================================================= -->

    <div class="card">

        <h1>
            Статистика
        </h1>


        <!-- =================================================
             Основные показатели
             ================================================= -->

        <div class="stats-grid">


            <div class="stat">

                <div class="stat-value">
                    <?= $totalWords ?>
                </div>

                <div class="stat-label">
                    слов в словаре
                </div>

            </div>


            <div class="stat">

                <div class="stat-value green">
                    <?= $masteredWords ?>
                </div>

                <div class="stat-label">
                    освоено слов
                </div>

                <div class="small">
                    <?= $masteredPercent ?>%
                </div>

            </div>


            <div class="stat">

                <div class="stat-value blue">
                    <?= $masteredWithoutErrors ?>
                </div>

                <div class="stat-label">
                    освоено без ошибок
                </div>

                <div class="small">
                    <?= $masteredWithoutErrorsPercent ?>%
                </div>

            </div>


        </div>


        <!-- =================================================
             Процентные показатели
             ================================================= -->

        <h2>
            Процентные показатели
        </h2>


        <table>

            <tr>

                <td>
                    Успешность воспроизведения
                </td>

                <td>
                    <strong>
                        <?= $knowledge ?>%
                    </strong>
                </td>

            </tr>


            <tr>

                <td>
                    Правописание
                </td>

                <td>
                    <strong>
                        <?= $spelling ?>%
                    </strong>
                </td>

            </tr>

        </table>


        <!-- =================================================
             Общая информация
             ================================================= -->

        <h2>
            Общая информация
        </h2>


        <table>

            <tr>

                <td>
                    Всего попыток
                </td>

                <td>
                    <?= $totalAttempts ?>
                </td>

            </tr>


            <tr>

                <td>
                    Правильных ответов
                </td>

                <td class="green">
                    <?= $totalCorrect ?>
                </td>

            </tr>


            <tr>

                <td>
                    Всего ошибок
                </td>

                <td class="red">
                    <?= $totalSpellingErrors + $totalUnknown ?>
                </td>

            </tr>


            <tr>

                <td>
                    &nbsp;&nbsp;Из них орфографических
                </td>

                <td class="yellow">
                    <?= $totalSpellingErrors ?>
                </td>

            </tr>


            <tr>

                <td>
                    &nbsp;&nbsp;Из них не знаешь слово
                </td>

                <td class="red">
                    <?= $totalUnknown ?>
                </td>

            </tr>

        </table>


        <!-- =================================================
             Слабые слова
             ================================================= -->

        <h2>
            Слабые слова
        </h2>


        <?php if (!$weakWords): ?>

            <p>
                Пока нет слабых слов.
            </p>

        <?php else: ?>


            <table>

                <thead>

                <tr>

                    <th>
                        Слово
                    </th>

                    <th>
                        Успешность
                    </th>

                    <th>
                        Попыток
                    </th>

                    <th>
                        Ошибок
                    </th>

                </tr>

                </thead>


                <tbody>


                <?php foreach (
                    array_slice(
                        $weakWords,
                        0,
                        20
                    )
                    as $item
                ): ?>


                    <tr>


                        <td>

                            <strong>
                                <?= e(
                                    $item['word']['word']
                                ) ?>
                            </strong>


                            <?php
                            $translations =
                                $item['word']['translations']
                                ?? [];
                            ?>


                            <?php if (
                                !empty($translations)
                            ): ?>

                                <br>

                                <span class="small">

                                    <?= e(
                                        $translations[0]
                                    ) ?>

                                </span>

                            <?php endif; ?>


                        </td>


                        <td>

                            <?= round(
                                $item['knowledge'],
                                1
                            ) ?>%

                        </td>


                        <td>

                            <?= $item['attempts'] ?>

                        </td>


                        <td>

                            <?= $item['errors'] ?>

                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>

            </table>


        <?php endif; ?>


    </div>

</div>

</body>

</html>
