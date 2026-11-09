<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/functions.php';

$vocabulary = getVocabulary();
$progress = getProgress();

if (count($vocabulary) === 0) {
    die('Dictionary is empty. Add some words first.');
}


/*
 * Начало нового теста.
 */
if (
    !isset($_SESSION['test'])
    || isset($_GET['new'])
) {
    $_SESSION['test'] = [
        'words' => createTest(
            $vocabulary,
            $progress
        ),
        'current' => 0,
        'results' => [],
        'started_at' => date('Y-m-d H:i:s')
    ];

    header('Location: index.php');
    exit;
}

$test = &$_SESSION['test'];


// =========================================================
// Если тест закончился
// =========================================================

if (
    $test['current'] >= count($test['words'])
) {

    $total = count($test['results']);

    $correct = 0;
    $partial = 0;
    $wrong = 0;
    $score = 0;


    foreach ($test['results'] as $result) {

        $score += $result['score'];


        if ($result['score'] === 2) {

            $correct++;

        } elseif ($result['score'] === 1) {

            $partial++;

        } else {

            $wrong++;
        }
    }


    // =====================================================
    // Считаем текущее количество освоенных слов.
    //
    // Важно:
    // progress уже содержит результаты всех ответов
    // текущего теста, потому что он обновляется сразу
    // после каждого ответа.
    // =====================================================

    $masteredWords = 0;

    $masteredWithoutErrors = 0;


    foreach ($vocabulary as $word) {

        $wordProgress = getWordProgress(
            $progress,
            (int)$word['id']
        );


        if (
            isWordMastered(
                $wordProgress
            )
        ) {
            $masteredWords++;
        }


        if (
            isWordMasteredWithoutSpellingErrors(
                $wordProgress
            )
        ) {
            $masteredWithoutErrors++;
        }
    }


    // =====================================================
    // Сохраняем результат теста в историю.
    // =====================================================

    saveTestHistory([

        'date' => date(
            'Y-m-d H:i:s'
        ),

        'mode' => 'spelling',

        'total' => $total,

        'correct' => $correct,

        'partial' => $partial,

        'wrong' => $wrong,

        'score' => $score,

        'max_score' => $total * 2,

        // Снимок прогресса на момент завершения теста.
        'mastered_words' => $masteredWords,

        'mastered_without_errors' =>
            $masteredWithoutErrors
    ]);


    $completedTest = $test;

    unset($_SESSION['test']);

    ?>

    <!DOCTYPE html>

    <html lang="ru">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Vocabulary Trainer</title>

        <link
            rel="stylesheet"
            href="style.css"
        >

    </head>

    <body>

    <div class="container">

        <div class="top-nav">

            <a href="index.php?new=1">
                Новый тест
            </a>

            <a href="stats.php">
                Статистика
            </a>

            <a href="history.php">
                История
            </a>

            <a href="add.php">
                Добавить слово
            </a>

        </div>


        <div class="card result-card">

            <h1>
                Тест завершён
            </h1>


            <div class="big-score">

                <?= $score ?>
                /
                <?= $total * 2 ?>

            </div>


            <div class="summary">

                <div>

                    <span class="green">
                        <?= $correct ?>
                    </span>

                    правильно

                </div>


                <div>

                    <span class="yellow">
                        <?= $partial ?>
                    </span>

                    ошибки

                </div>


                <div>

                    <span class="red">
                        <?= $wrong ?>
                    </span>

                    не знаю

                </div>

            </div>


            <div class="test-progress">

                <p>

                    Освоено слов:
                    <strong>
                        <?= $masteredWords ?>
                    </strong>

                </p>


                <p>

                    Освоено без ошибок:
                    <strong>
                        <?= $masteredWithoutErrors ?>
                    </strong>

                </p>

            </div>


            <a
                class="button"
                href="index.php?new=1"
            >
                Пройти ещё раз
            </a>

        </div>

    </div>

    </body>

    </html>

    <?php

    exit;
}


// =========================================================
// Текущее слово
// =========================================================

$currentWordId =
    (int)$test['words'][$test['current']];


$currentWord = findWord(
    $vocabulary,
    $currentWordId
);


if ($currentWord === null) {
    die('Word not found.');
}


// =========================================================
// Обработка ответа
// =========================================================

$result = null;


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $answer =
        $_POST['answer'] ?? '';


    $correctWord =
        $currentWord['word'];


    $result = calculateScore(
        $answer,
        $correctWord
    );


    // -----------------------------------------------------
    // Обновляем progress
    // -----------------------------------------------------

    updateWordProgress(
        $progress,
        $currentWordId,
        $result['score']
    );


    writeJson(
        PROGRESS_FILE,
        $progress
    );


    // -----------------------------------------------------
    // Запоминаем результат текущего вопроса
    // -----------------------------------------------------

    $test['results'][] = [

        'word_id' =>
            $currentWordId,

        'answer' =>
            $answer,

        'correct_answer' =>
            $correctWord,

        'score' =>
            $result['score'],

        'distance' =>
            $result['distance']
    ];


    $test['current']++;


    /*
     * После ответа показываем результат.
     * Следующий вопрос появится после нажатия кнопки.
     */
}


// =========================================================
// Текущий вопрос
// =========================================================

$questionNumber =
    $test['current'];


$totalQuestions =
    count($test['words']);

?>

<!DOCTYPE html>

<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Vocabulary Trainer</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>


<body>

<div class="container">


    <div class="top-nav">
        <a href="index.php?new=1">
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


    <?php if ($result === null): ?>


        <div class="card test-card">

            <div class="progress">

                Вопрос
                <?= $questionNumber + 1 ?>
                /
                <?= $totalQuestions ?>

            </div>


            <h1>

                <?= e(
                    $currentWord['translations'][0]
                ) ?>

            </h1>


            <form method="post">

                <input
                    type="text"
                    name="answer"
                    autocomplete="off"
                    autofocus
                    placeholder="Введите английское слово"
                >


                <button type="submit">
                    Проверить
                </button>

            </form>

        </div>


    <?php else: ?>


        <div class="card result-card">


            <?php if ($result['score'] === 2): ?>


                <div class="result-icon green">
                    ✓
                </div>


                <h1>
                    Правильно!
                </h1>


                <div class="correct-word">

                    <?= e(
                        $currentWord['word']
                    ) ?>

                </div>


                <p>
                    Отлично. Слово и орфография
                    запомнены правильно.
                </p>


            <?php elseif ($result['score'] === 1): ?>


                <div class="result-icon yellow">
                    ~
                </div>


                <h1>
                    Почти!
                </h1>


                <div class="correct-word">

                    <?= e(
                        $currentWord['word']
                    ) ?>

                </div>


                <p>

                    Ты знаешь слово, но есть
                    орфографическая ошибка.

                </p>


                <p class="small">

                    Твой ответ:

                    <strong>

                        <?= e(
                            (string)$test['results'][
                                array_key_last(
                                    $test['results']
                                )
                            ]['answer']
                        ) ?>

                    </strong>

                </p>


            <?php else: ?>


                <div class="result-icon red">
                    ×
                </div>


                <h1>
                    Пока не знаешь
                </h1>


                <div class="correct-word">

                    <?= e(
                        $currentWord['word']
                    ) ?>

                </div>


                <p>

                    Правильный ответ:

                    <strong>
                        <?= e(
                            $currentWord['word']
                        ) ?>
                    </strong>

                </p>


                <p class="small">

                    Твой ответ:

                    <strong>

                        <?= e(
                            (string)$test['results'][
                                array_key_last(
                                    $test['results']
                                )
                            ]['answer']
                        ) ?>

                    </strong>

                </p>


            <?php endif; ?>


            <?php if (
                $test['current'] <
                $totalQuestions
            ): ?>


                <a
                    class="button"
                    href="index.php"
                >
                    Следующее слово
                </a>


            <?php else: ?>


                <a
                    class="button"
                    href="index.php"
                >
                    Результат теста
                </a>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>

</body>

</html>