<?php

namespace Tests\Feature;

use App\Mail\CustomerSupportMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_contact_page_renders_from_the_storefront(): void
    {
        $this->get(route('shop.contact'))
            ->assertOk()
            ->assertSee('VOID Support')
            ->assertSee('Send message');
    }

    public function test_a_customer_can_send_a_support_message(): void
    {
        Mail::fake();

        $this->post(route('shop.contact.send'), [
            'email' => 'customer@example.com',
            'message' => 'I have a question about the sizing of my order.',
        ])->assertRedirect(route('shop.contact'));

        Mail::assertSent(CustomerSupportMessage::class, function (CustomerSupportMessage $mail): bool {
            $body = $mail->render();

            return $mail->customerEmail === 'customer@example.com'
                && $mail->customerMessage === 'I have a question about the sizing of my order.'
                && str_contains($body, 'I have a question about the sizing of my order.');
        });
    }

    public function test_an_invalid_support_message_is_rejected(): void
    {
        Mail::fake();

        $this->from(route('shop.contact'))
            ->post(route('shop.contact.send'), [
                'email' => 'not-an-email',
                'message' => '',
            ])
            ->assertSessionHasErrors(['email', 'message']);

        Mail::assertNothingSent();
    }
}
