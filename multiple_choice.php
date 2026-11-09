<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/functions.php';

$vocabulary = getVocabulary();

if (count($vocabulary) < 6) {
    die(
        'Для теста «Выбрать перевод» необходимо минимум 6 слов в словаре.'
    );
}


/*
 * =========================================================
 * Начало нового теста
 * =========================================================
 */

if (
    !isset($_SESSION['multiple_choice_test'])
    || isset($_GET['new'])
) {

    $progress = getProgress();

    $_SESSION['multiple_choice_test'] = [
        'words' => createTest(
            $vocabulary,
            $progress
        ),

        'current' => 0,

        'results' => [],

        'started_at' =>
            date('Y-m-d H:i:s')
    ];


    header(
        'Location: multiple_choice.php'
    );

    exit;
}


$test =
    &$_SESSION['multiple_choice_test'];


// =========================================================
// Если тест закончился
// =========================================================

if (
    $test['current'] >=
    count($test['words'])
) {

    $total =
        count($test['results']);

    $correct = 0;
    $wrong = 0;


    foreach ($test['results'] as $result) {

        if (
            $result['correct'] === true
        ) {

            $correct++;

        } else {

            $wrong++;
        }
    }


    $score = $correct;


    // -----------------------------------------------------
    // Сохраняем отдельную историю.
    // -----------------------------------------------------

    saveChoiceTestHistory([

        'date' =>
            date('Y-m-d H:i:s'),

        'total' =>
            $total,

        'correct' =>
            $correct,

        'wrong' =>
            $wrong,

        'score' =>
            $score,

        'max_score' =>
            $total

    ]);


    $completedTest = $test;


    unset(
        $_SESSION['multiple_choice_test']
    );

    ?>

    <!DOCTYPE html>

    <html lang="ru">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Выбрать перевод</title>

        <link
            rel="stylesheet"
            href="style.css"
        >

    </head>


    <body>

    <div class="container">


        <div class="top-nav">

            <a href="index.php">
                Тест «Написать перевод»
            </a>

            <a href="multiple_choice.php?new=1">
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


        <div class="card result-card">

            <h1>
                Тест завершён
            </h1>


            <div class="big-score">

                <?= $score ?>
                /
                <?= $total ?>

            </div>


            <div class="summary">

                <div>

                    <span class="green">
                        <?= $correct ?>
                    </span>

                    правильно

                </div>


                <div>

                    <span class="red">
                        <?= $wrong ?>
                    </span>

                    ошибок

                </div>

            </div>


            <a
                class="button"
                href="multiple_choice.php?new=1"
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
    (int)$test['words'][
        $test['current']
    ];


$currentWord = findWord(
    $vocabulary,
    $currentWordId
);


if ($currentWord === null) {
    die('Word not found.');
}


// =========================================================
// Подготавливаем варианты
// =========================================================

$options = createMultipleChoiceOptions(
    $vocabulary,
    $currentWord
);


if (count($options) < 2) {
    die(
        'Не удалось создать варианты ответа.'
    );
}


// Правильный ответ выбираем из переводов.
$correctTranslations =
    getWordTranslations(
        $currentWord
    );


// Для проверки запоминаем все допустимые
// переводы текущего слова.
$questionCorrectTranslations =
    $correctTranslations;


// =========================================================
// Обработка ответа
// =========================================================

$result = null;


if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $answer =
        $_POST['answer'] ?? '';


    $isCorrect = false;


    foreach (
        $questionCorrectTranslations
        as $translation
    ) {

        if (
            normalizeAnswer($answer) ===
            normalizeAnswer(
                (string)$translation
            )
        ) {

            $isCorrect = true;

            break;
        }
    }


    $result = [

        'answer' =>
            $answer,

        'correct' =>
            $isCorrect,

        'correct_answers' =>
            $questionCorrectTranslations
    ];


    // -----------------------------------------------------
    // Сохраняем результат текущего вопроса.
    // -----------------------------------------------------

    $test['results'][] = [

        'word_id' =>
            $currentWordId,

        'answer' =>
            $answer,

        'correct' =>
            $isCorrect
    ];


    $test['current']++;
}


$questionNumber =
    $test['current'];


$totalQuestions =
    count($test['words']);


// =========================================================
// HTML
// =========================================================

?>

<!DOCTYPE html>

<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Выбрать перевод</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

    <style>

        .choice-timer {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }

        .choice-timer.warning {
            color: #d97706;
        }

        .choice-timer.danger {
            color: #dc2626;
        }

        .choice-options {
            display: grid;
            gap: 10px;
            margin-top: 20px;
        }

        .choice-option {
            width: 100%;
            padding: 14px 18px;
            font-size: 18px;
            cursor: pointer;
        }

        .choice-option.correct-answer {
            background: #dcfce7;
            border-color: #16a34a;
        }

        .choice-option.wrong-answer {
            background: #fee2e2;
            border-color: #dc2626;
        }

    </style>

</head>


<body>

<div class="container">


    <div class="top-nav">

        <a href="index.php">
            Тест «Написать перевод»
        </a>

        <a href="multiple_choice.php?new=1">
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
                    $currentWord['word']
                ) ?>

            </h1>


            <div
                id="timer"
                class="choice-timer"
            >
                5
            </div>


            <form
                method="post"
                id="choice-form"
            >

                <div class="choice-options">


                    <?php foreach (
                        $options as $option
                    ): ?>


                        <button
                            type="submit"
                            name="answer"
                            value="<?= e($option) ?>"
                            class="choice-option"
                        >

                            <?= e($option) ?>

                        </button>


                    <?php endforeach; ?>


                </div>

            </form>


            <form
                method="post"
                id="timeout-form"
                style="display:none;"
            >

                <input
                    type="hidden"
                    name="answer"
                    value=""
                >

            </form>


        </div>


        <script>

            const timerElement =
                document.getElementById('timer');

            const choiceForm =
                document.getElementById('choice-form');

            const timeoutForm =
                document.getElementById('timeout-form');


            let seconds = 5;


            const timer = setInterval(
                function () {

                    seconds--;

                    timerElement.textContent =
                        seconds;


                    if (seconds <= 2) {

                        timerElement.classList.add(
                            'warning'
                        );
                    }


                    if (seconds <= 1) {

                        timerElement.classList.remove(
                            'warning'
                        );

                        timerElement.classList.add(
                            'danger'
                        );
                    }


                    if (seconds <= 0) {

                        clearInterval(timer);

                        timeoutForm.submit();
                    }

                },
                1000
            );


            // После ручного выбора
            // таймер больше не нужен.
            choiceForm.addEventListener(
                'submit',
                function () {

                    clearInterval(timer);

                }
            );

        </script>


    <?php else: ?>


        <div class="card result-card">


            <?php if (
                $result['correct']
            ): ?>


                <div class="result-icon green">
                    ✓
                </div>


                <h1>
                    Правильно!
                </h1>


                <p>
                    Перевод выбран правильно.
                </p>


            <?php else: ?>


                <div class="result-icon red">
                    ×
                </div>


                <h1>
                    Неверно
                </h1>


                <p>

                    Правильный перевод:

                </p>


            <?php endif; ?>


            <div class="correct-word">

                <?php

                echo e(
                    implode(
                        ', ',
                        $questionCorrectTranslations
                    )
                );

                ?>

            </div>


            <?php if (
                $result['answer'] === ''
            ): ?>

                <p class="small">
                    Время вышло.
                </p>

            <?php else: ?>

                <p class="small">

                    Твой ответ:

                    <strong>
                        <?= e(
                            $result['answer']
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
                    href="multiple_choice.php"
                >
                    Следующее слово
                </a>


            <?php else: ?>


                <a
                    class="button"
                    href="multiple_choice.php"
                >
                    Результат теста
                </a>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>

</body>

</html>
