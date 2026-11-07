<?php

namespace Tests\Feature\Buyer;

use App\Services\Assistant\ShopAssistant;
use Tests\TestCase;

/** The assistant is ALLIE: short for ALVY, named after someone special, originally ALIE. */
class AssistantNameTest extends TestCase
{
    private function answer(string $question): string
    {
        return app(ShopAssistant::class)->quickAnswer($question, null);
    }

    public function test_name_questions_get_the_story(): void
    {
        foreach ([
            'What is your name?', "What's your name?", 'Where did you get your name?', 'Why are you called ALLIE?',
            'Who named you?', 'What does ALLIE mean?', 'Hi! what is your name', 'ALLIE, where does your name come from?',
        ] as $question) {
            $answer = $this->answer($question);
            $this->assertStringContainsString("I'm ALLIE", $answer, $question);
            $this->assertStringContainsString('short for ALVY', $answer, $question);
            $this->assertStringContainsString('named after someone special', $answer, $question);
            $this->assertStringContainsString('actually ALIE', $answer, $question);
        }
    }

    public function test_a_greeting_with_the_question_has_one_emoji(): void
    {
        $answer = $this->answer('Hi! what is your name');
        $this->assertStringStartsWith("Hi! I'm ALLIE! 😊", $answer);
        $this->assertSame(1, mb_substr_count($answer, '😊'));
    }

    public function test_tagalog_name_questions_get_the_story_in_tagalog(): void
    {
        foreach (['Ano pangalan mo?', 'Bakit ALLIE?', 'Saan galing ang pangalan mo?', 'Sino nagpangalan sa iyo?'] as $question) {
            $answer = $this->answer($question);
            $this->assertStringContainsString('Ako si ALLIE', $answer, $question);
            $this->assertStringContainsString('pinaikling ALVY', $answer, $question);
            $this->assertStringContainsString('espesyal na tao', $answer, $question);
            $this->assertStringContainsString('ALIE', $answer, $question);
        }
    }

    public function test_who_are_you_introduces_allie(): void
    {
        $this->assertStringStartsWith("I'm ALLIE, ALVY's shopping assistant!", $this->answer('Who are you?'));
        $this->assertStringStartsWith('Ako si ALLIE', $this->answer('Sino ka?'));
    }

    public function test_the_special_someone_stays_a_secret(): void
    {
        $this->assertStringContainsString('little secret', $this->answer('Who is that someone special?'));
    }

    public function test_other_questions_are_not_mistaken_for_name_questions(): void
    {
        $assistant = app(ShopAssistant::class);
        foreach (['Change my name on my account', 'What is the name of this product?', 'bag under 300', 'Where is my order?', 'Hi ALLIE'] as $question) {
            $this->assertNull($assistant->nameAnswer(mb_strtolower($question)), $question);
        }
        $this->assertSame('Hi there! 👋 Welcome to ALVY! How can I help you today?', $this->answer('Hi ALLIE'));
    }
}
