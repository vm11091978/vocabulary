<?php

declare(strict_types=1);

// =========================================================
// Настройки
// =========================================================

const DATA_DIR = __DIR__ . '/data';

const VOCABULARY_FILE = DATA_DIR . '/vocabulary.json';
const PROGRESS_FILE   = DATA_DIR . '/progress.json';
const HISTORY_FILE    = DATA_DIR . '/history.json';

const QUESTIONS_PER_TEST = 10;
/*
const LENGTH_FOR_ERROR_1 = 4; // если слово длиннее этого значения, допускается 1 ошибка
const LENGTH_FOR_ERROR_2 = 8; // если слово длиннее этого значения, допускаются 2 ошибки
const LENGTH_FOR_ERROR_3 = 12; // если слово длиннее этого значения, допускаются 3 ошибки
*/


// =========================================================
// Работа с JSON
// =========================================================

/**
 * Читает JSON-файл.
 *
 * Если файл не существует, пустой или содержит
 * некорректный JSON — возвращает $default.
 */
function readJson(string $file, mixed $default): mixed
{
    if (!file_exists($file)) {
        return $default;
    }

    $content = file_get_contents($file);

    if ($content === false || trim($content) === '') {
        return $default;
    }

    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $default;
    }

    return $data;
}


/**
 * Записывает данные в JSON-файл.
 *
 * Используем блокировку файла, чтобы избежать
 * проблем при одновременной записи.
 */
function writeJson(string $file, mixed $data): void
{
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        throw new RuntimeException(
            'Не удалось преобразовать данные в JSON.'
        );
    }

    $handle = fopen($file, 'c+');

    if ($handle === false) {
        throw new RuntimeException(
            "Не удалось открыть файл: {$file}"
        );
    }

    try {

        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException(
                "Не удалось заблокировать файл: {$file}"
            );
        }

        ftruncate($handle, 0);

        rewind($handle);

        fwrite($handle, $json);

        fflush($handle);

        flock($handle, LOCK_UN);

    } finally {

        fclose($handle);
    }
}


// =========================================================
// Данные приложения
// =========================================================

/**
 * Возвращает словарь.
 */
function getVocabulary(): array
{
    return readJson(
        VOCABULARY_FILE,
        []
    );
}


/**
 * Возвращает статистику слов.
 */
function getProgress(): array
{
    return readJson(
        PROGRESS_FILE,
        []
    );
}


/**
 * Возвращает историю тестов.
 */
function getHistory(): array
{
    return readJson(
        HISTORY_FILE,
        []
    );
}


// =========================================================
// Вспомогательные функции
// =========================================================

/**
 * Безопасный вывод текста в HTML.
 */
function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/**
 * Нормализует ответ пользователя.
 *
 * Например:
 *
 * " Necessary "
 * "NECESSARY"
 *
 * превратятся в:
 *
 * "necessary"
 */
function normalizeAnswer(string $answer): string
{
    $answer = trim(
        mb_strtolower($answer)
    );

    $answer = preg_replace(
        '/\s+/',
        ' ',
        $answer
    );

    return $answer ?? '';
}


// =========================================================
// Проверка написания
// =========================================================

/**
 * Определяет количество допустимых
 * орфографических ошибок.
 *
 * 1–3 символа  → 0 ошибок
 * 4–11 символов → 1 ошибка
 * 12+ символов → 2 ошибки
 */
function allowedSpellingErrors(string $word): int
{
    $length = mb_strlen($word);

    if ($length <= 3) {
        return 0;
    }

    if ($length >= 12) {
        return 2;
    }

    return 1;
}


/**
 * Проверяет ответ пользователя.
 *
 * Возвращает:
 *
 * score = 2
 * полностью правильный ответ
 *
 * score = 1
 * слово знаем, но есть небольшая
 * орфографическая ошибка
 *
 * score = 0
 * слово не знаем
 */
function calculateScore(
    string $answer,
    string $correct
): array {

    $answer = normalizeAnswer($answer);

    $correct = normalizeAnswer($correct);


    // Полностью правильный ответ.
    if ($answer === $correct) {

        return [
            'score' => 2,
            'distance' => 0,
            'correct' => true
        ];
    }


    // Расстояние Левенштейна.
    $distance = levenshtein(
        $answer,
        $correct
    );


    $allowed = allowedSpellingErrors(
        $correct
    );


    // Небольшая орфографическая ошибка.
    if ($distance <= $allowed) {

        return [
            'score' => 1,
            'distance' => $distance,
            'correct' => false
        ];
    }


    // Слово не знаем.
    return [
        'score' => 0,
        'distance' => $distance,
        'correct' => false
    ];
}


// =========================================================
// Статистика отдельного слова
// =========================================================

/**
 * Возвращает статистику конкретного слова.
 *
 * Для старых записей добавляем отсутствующие поля,
 * чтобы после обновления программы ничего не сломалось.
 */
function getWordProgress(
    array $progress,
    int $wordId
): array {

    $default = [
        'attempts' => 0,

        // Количество полностью правильных ответов.
        'correct' => 0,

        // Количество ответов с небольшой
        // орфографической ошибкой.
        'spelling_errors' => 0,

        // Количество случаев, когда слово
        // фактически не было воспроизведено.
        'unknown' => 0,

        // Общая сумма баллов.
        'spelling_score' => 0,

        // Количество всех ошибок.
        'mistakes' => 0,

        // Дата последней попытки.
        'last_seen' => null,

        // Последние три результата.
        'recent_scores' => []
    ];


    $key = (string)$wordId;


    if (!isset($progress[$key])) {
        return $default;
    }


    // Объединяем существующие данные
    // с новыми значениями по умолчанию.
    return array_merge(
        $default,
        $progress[$key]
    );
}


/**
 * Обновляет статистику конкретного слова.
 */
function updateWordProgress(
    array &$progress,
    int $wordId,
    int $score
): void {

    $key = (string)$wordId;


    // Если слово встречается впервые,
    // создаём его статистику.
    if (!isset($progress[$key])) {

        $progress[$key] = [
            'attempts' => 0,
            'correct' => 0,
            'spelling_errors' => 0,
            'unknown' => 0,
            'spelling_score' => 0,
            'mistakes' => 0,
            'last_seen' => null,
            'recent_scores' => []
        ];
    }


    // Всегда увеличиваем количество попыток.
    $progress[$key]['attempts']++;


    // -----------------------------------------------------
    // Результат = 2
    // -----------------------------------------------------

    if ($score === 2) {

        $progress[$key]['correct']++;

    }


    // -----------------------------------------------------
    // Результат = 1
    // -----------------------------------------------------

    elseif ($score === 1) {

        $progress[$key]['spelling_errors']++;

    }


    // -----------------------------------------------------
    // Результат = 0
    // -----------------------------------------------------

    else {

        $progress[$key]['unknown']++;

    }


    // Общая сумма баллов.
    $progress[$key]['spelling_score'] += $score;


    // Любой результат меньше 2 —
    // это ошибка.
    if ($score < 2) {

        $progress[$key]['mistakes']++;

    }


    // Время последней попытки.
    $progress[$key]['last_seen'] =
        date('Y-m-d H:i:s');


    // -----------------------------------------------------
    // Последние три результата
    // -----------------------------------------------------

    $progress[$key]['recent_scores'][] = $score;


    $progress[$key]['recent_scores'] =
        array_slice(
            $progress[$key]['recent_scores'],
            -3
        );
}


// =========================================================
// Определение освоенности слова
// =========================================================

/**
 * Слово считается освоенным, если:
 *
 * - было минимум 3 последние попытки;
 * - во всех трёх случаях пользователь
 *   либо ответил правильно, либо допустил
 *   небольшую орфографическую ошибку.
 *
 * То есть допустимы:
 *
 * 2, 2, 2
 * 2, 1, 2
 * 1, 1, 2
 * и т.д.
 *
 * Но наличие 0 означает, что слово
 * пока не считается освоенным.
 */
function isWordMastered(
    array $wordProgress
): bool {

    $scores =
        $wordProgress['recent_scores'] ?? [];


    if (count($scores) < 3) {
        return false;
    }


    foreach ($scores as $score) {

        if ($score < 1) {
            return false;
        }
    }


    return true;
}


/**
 * Слово считается освоенным без ошибок,
 * если последние три попытки были
 * полностью правильными.
 */
function isWordMasteredWithoutSpellingErrors(
    array $wordProgress
): bool {

    $scores =
        $wordProgress['recent_scores'] ?? [];


    if (count($scores) < 3) {
        return false;
    }


    foreach ($scores as $score) {

        if ($score !== 2) {
            return false;
        }
    }


    return true;
}


// =========================================================
// Выбор слов для теста
// =========================================================

/**
 * Рассчитывает приоритет слова.
 *
 * Пока используем простой MVP-алгоритм:
 *
 * priority = weight + mistakes
 *
 * Чем чаще ошибались в слове,
 * тем чаще оно будет появляться.
 */
function getWordPriority(
    array $word,
    array $progress
): int {

    $wordProgress = getWordProgress(
        $progress,
        (int)$word['id']
    );


    $weight = (int)(
        $word['weight'] ?? 1
    );


    $mistakes = (int)(
        $wordProgress['mistakes'] ?? 0
    );


    return max(
        1,
        $weight + $mistakes
    );
}


/**
 * Выбирает одно слово случайно,
 * учитывая его приоритет.
 */
function weightedRandomWord(
    array $vocabulary,
    array $progress,
    array $excludeIds = []
): ?array {

    $candidates = [];


    foreach ($vocabulary as $word) {

        $id = (int)$word['id'];


        // Не используем слово,
        // которое уже есть в текущем тесте.
        if (
            in_array(
                $id,
                $excludeIds,
                true
            )
        ) {
            continue;
        }


        $priority = getWordPriority(
            $word,
            $progress
        );


        $candidates[] = [
            'word' => $word,
            'priority' => $priority
        ];
    }


    if (!$candidates) {
        return null;
    }


    // -----------------------------------------------------
    // Общий вес всех кандидатов.
    // -----------------------------------------------------

    $total = 0;


    foreach ($candidates as $candidate) {

        $total += (int)$candidate['priority'];
    }


    // -----------------------------------------------------
    // Выбираем случайную точку
    // внутри общего диапазона.
    // -----------------------------------------------------

    $random = random_int(
        1,
        $total
    );


    foreach ($candidates as $candidate) {

        $random -=
            (int)$candidate['priority'];


        if ($random <= 0) {

            return $candidate['word'];
        }
    }


    // Теоретически сюда попасть нельзя,
    // но возвращаем последнее слово
    // на всякий случай.
    return $candidates[
        array_key_last($candidates)
    ]['word'];
}


/**
 * Создаёт новый тест.
 */
function createTest(
    array $vocabulary,
    array $progress
): array {

    $test = [];

    $usedIds = [];


    $count = min(
        QUESTIONS_PER_TEST,
        count($vocabulary)
    );


    for ($i = 0; $i < $count; $i++) {

        $word = weightedRandomWord(
            $vocabulary,
            $progress,
            $usedIds
        );


        if ($word === null) {
            break;
        }


        $wordId = (int)$word['id'];


        $test[] = $wordId;

        $usedIds[] = $wordId;
    }


    return $test;
}


/**
 * Находит слово по ID.
 */
function findWord(
    array $vocabulary,
    int $id
): ?array {

    foreach ($vocabulary as $word) {

        if (
            (int)$word['id'] === $id
        ) {
            return $word;
        }
    }


    return null;
}


// =========================================================
// Общие процентные показатели
// =========================================================

/**
 * Успешность воспроизведения.
 *
 * Ответы 1 и 2 считаются успешным
 * воспроизведением слова.
 *
 * Формула:
 *
 * (correct + spelling_errors)
 * / attempts * 100
 */
function calculateKnowledgePercent(
    array $vocabulary,
    array $progress
): float {

    $totalAttempts = 0;

    $successfulAnswers = 0;


    foreach ($vocabulary as $word) {

        $p = getWordProgress(
            $progress,
            (int)$word['id']
        );


        $totalAttempts +=
            $p['attempts'];


        $successfulAnswers +=
            $p['correct'] +
            $p['spelling_errors'];
    }


    if ($totalAttempts === 0) {
        return 0;
    }


    return round(
        (
            $successfulAnswers /
            $totalAttempts
        ) * 100,
        1
    );
}


/**
 * Правописание.
 *
 * Каждый ответ имеет максимум 2 балла:
 *
 * 2 = полностью правильно
 * 1 = небольшая ошибка
 * 0 = неизвестное слово
 */
function calculateSpellingPercent(
    array $vocabulary,
    array $progress
): float {

    $totalAttempts = 0;

    $totalScore = 0;


    foreach ($vocabulary as $word) {

        $p = getWordProgress(
            $progress,
            (int)$word['id']
        );


        $totalAttempts +=
            $p['attempts'];


        $totalScore +=
            $p['spelling_score'];
    }


    if ($totalAttempts === 0) {
        return 0;
    }


    return round(
        (
            $totalScore /
            ($totalAttempts * 2)
        ) * 100,
        1
    );
}


// =========================================================
// История тестов
// =========================================================

/**
 * Добавляет результат теста в историю.
 */
function saveTestHistory(
    array $result
): void {

    $history = getHistory();


    $history[] = $result;


    writeJson(
        HISTORY_FILE,
        $history
    );
}


// =========================================================
// История теста «Выбрать перевод»
// =========================================================

const CHOICE_HISTORY_FILE = DATA_DIR . '/choice_history.json';


/**
 * Возвращает историю тестов «Выбрать перевод».
 */
function getChoiceHistory(): array
{
    return readJson(
        CHOICE_HISTORY_FILE,
        []
    );
}


/**
 * Сохраняет результат теста «Выбрать перевод».
 */
function saveChoiceTestHistory(
    array $result
): void {

    $history = getChoiceHistory();

    $history[] = $result;

    writeJson(
        CHOICE_HISTORY_FILE,
        $history
    );
}


/**
 * Проверяет, является ли ответ одним
 * из допустимых переводов слова.
 */
function isCorrectTranslation(
    string $answer,
    array $word
): bool {

    $answer = normalizeAnswer($answer);

    $translations =
        $word['translations'] ?? [];


    foreach ($translations as $translation) {

        if (
            normalizeAnswer(
                (string)$translation
            ) === $answer
        ) {
            return true;
        }
    }


    return false;
}


/**
 * Получает список переводов слова.
 */
function getWordTranslations(
    array $word
): array {

    $translations =
        $word['translations'] ?? [];


    $result = [];


    foreach ($translations as $translation) {

        $translation =
            trim((string)$translation);


        if ($translation === '') {
            continue;
        }


        if (
            !in_array(
                $translation,
                $result,
                true
            )
        ) {
            $result[] = $translation;
        }
    }


    return $result;
}


/**
 * Создаёт 6 вариантов ответа для вопроса.
 *
 * Один вариант обязательно правильный.
 * Остальные пять берутся из других слов словаря.
 */
function createMultipleChoiceOptions(
    array $vocabulary,
    array $correctWord
): array {

    $correctTranslations =
        getWordTranslations(
            $correctWord
        );


    if (!$correctTranslations) {
        return [];
    }


    // Для вопроса выбираем один из допустимых
    // переводов как правильный вариант.
    $correctAnswer =
        $correctTranslations[
            array_rand(
                $correctTranslations
            )
        ];


    $options = [
        $correctAnswer
    ];


    // -----------------------------------------------------
    // Собираем неправильные варианты.
    // -----------------------------------------------------

    $wrongOptions = [];


    foreach ($vocabulary as $word) {

        // Само слово исключаем.
        if (
            (int)$word['id'] ===
            (int)$correctWord['id']
        ) {
            continue;
        }


        $translations =
            getWordTranslations($word);


        foreach ($translations as $translation) {

            // Не добавляем правильный вариант.
            if (
                normalizeAnswer($translation) ===
                normalizeAnswer($correctAnswer)
            ) {
                continue;
            }


            if (
                !in_array(
                    $translation,
                    $wrongOptions,
                    true
                )
            ) {

                $wrongOptions[] =
                    $translation;
            }
        }
    }


    // Перемешиваем неправильные варианты.
    shuffle($wrongOptions);


    // Берём максимум пять.
    $wrongOptions =
        array_slice(
            $wrongOptions,
            0,
            5
        );


    $options = array_merge(
        $options,
        $wrongOptions
    );


    // Если словарь слишком маленький
    // и не удалось набрать 6 вариантов,
    // возвращаем столько, сколько есть.
    shuffle($options);


    return $options;
}
