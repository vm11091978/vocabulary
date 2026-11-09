<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$history = getHistory();

$history = array_reverse($history);

?>
<!DOCTYPE html>

<html lang="ru">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>History</title>

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

        <h1>
            История
        </h1>


        <?php if (!$history): ?>


            <p>
                Пока тестов нет.
            </p>


        <?php else: ?>


            <table>

                <thead>

                <tr>

                    <th>
                        Дата
                    </th>

                    <th>
                        Результат
                    </th>

                    <th>
                        Правильно
                    </th>

                    <th>
                        Ошибки
                    </th>

                    <th>
                        Не знаю
                    </th>

                    <th>
                        Освоено
                    </th>

                    <th>
                        Без ошибок
                    </th>

                </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $history as $test
                ): ?>


                    <tr>


                        <td>

                            <?= e(
                                (string)(
                                    $test['date']
                                    ?? ''
                                )
                            ) ?>

                        </td>


                        <td>

                            <strong>

                                <?= (int)(
                                    $test['score']
                                    ?? 0
                                ) ?>

                                /

                                <?= (int)(
                                    $test['max_score']
                                    ?? 0
                                ) ?>

                            </strong>

                        </td>


                        <td class="green">

                            <?= (int)(
                                $test['correct']
                                ?? 0
                            ) ?>

                        </td>


                        <td class="yellow">

                            <?= (int)(
                                $test['partial']
                                ?? 0
                            ) ?>

                        </td>


                        <td class="red">

                            <?= (int)(
                                $test['wrong']
                                ?? 0
                            ) ?>

                        </td>


                        <td>

                            <?php if (
                                isset(
                                    $test['mastered_words']
                                )
                            ): ?>

                                <strong>

                                    <?= (int)(
                                        $test[
                                            'mastered_words'
                                        ]
                                    ) ?>

                                </strong>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td>

                            <?php if (
                                isset(
                                    $test[
                                        'mastered_without_errors'
                                    ]
                                )
                            ): ?>

                                <strong>

                                    <?= (int)(
                                        $test[
                                            'mastered_without_errors'
                                        ]
                                    ) ?>

                                </strong>

                            <?php else: ?>

                                —

                            <?php endif; ?>

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
