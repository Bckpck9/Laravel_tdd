<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_can_be_created(): void{
        $response = $this->post('/posts', ['title' => 'My first post', 'image' => UploadedFile::fake()->image('post.jpg'),]);

        $response -> assertStatus(201);

        $this->assertDatabaseHas('posts',['title' => 'My first post']);
    }

    public function test_post_title_is_required(){
        $response = $this->post('/posts',['title' => '',]);

        $response -> assertSessionHasErrors('title');

        $this->assertDatabaseCount('posts',0);
    }

    public function test_post_can_be_created_with_image(){
        Storage::fake('public');

        $image = UploadedFile::fake()->image('post.jpg');

        $response = $this->post('/posts',['title' => 'Post with image', 'image' => $image]);

        $response->assertStatus(201);

        $post = Post::where('title', 'Post with image')->firstOrFail();

        Storage::disk('public')->assertExists($post->image);
    }

    public function test_post_image_is_required(){
        $response = $this->post('/posts',['title' => 'Post without image']);

        $response -> assertSessionHasErrors('image');

        $this->assertDatabaseCount('posts',0);
    }

    public function test_post_image_must_be_image(){
        Storage::fake('public');
        $file=UploadedFile::fake()->create('ducument.pdf',100);
        $response = $this->post('/posts',['title' => 'Post without image','image' => $file]);
        $response -> assertSessionHasErrors('image');

        $this->assertDatabaseCount('posts',0);
    }

    public function test_post_can_be_updated(){
        $user = User::factory()->create();
        $post = Post::create([
            'title' => 'Old title',
            'image' => 'posts/old.jpg',
            'user_id' => $user->id
        ]);

        $response = $this->put("/posts/{$post->id}",[
            'title' => 'New title',
            'image' => UploadedFile::fake()->image('new.jpg')
        ]);

        $response -> assertSessionHasNoErrors();

        $this -> assertDatabaseHas('posts',[
            'id' => $post->id,
            'title' => 'New title'
        ]);
    }

    public function test_post_index_returns_all_posts(){
        $user = User::factory()->create();
        Post::create([
            'title' => 'First post',
            'image' => 'posts/first.jpg',
            'user_id' => $user->id
        ]);

        Post::create([
            'title' => 'Second post',
            'image' => 'posts/second.jpg',
            'user_id' => $user->id
        ]);

        $response = $this->get('/posts');

        $response->assertOk();

        $response->assertSee('First post');
        $response->assertSee('Second post');
    }

    public function test_post_show_returns_one_post(){
        $user = User::factory()->create();
        $post = Post::create([
            'title' => 'My test post',
            'image' => 'posts/test.jpg',
            'user_id' => $user->id
        ]);

        $response = $this -> get("/posts/{$post->id}");

        $response -> assertOk();
        $response -> assertJsonFragment([
            'id' => $post->id,
            'title' => 'My test post'
        ]);
    }

    public function test_post_can_be_deleted(){
        $user = User::factory()->create();
        $post = Post::create([
            'title' => 'Post to delete',
            'image' => 'posts/test.jpg',
            'user_id' => $user->id
        ]);

        $response = $this -> delete("/posts/{$post->id}");

        $response ->assertOk();

        $this->assertDatabaseMissing('posts',[
            'id' => $post->id
        ]);
    }

    public function test_post_cannot_be_deleted_if_it_does_not_exists(){
        $response = $this->delete('/posts/99999');

        $response->assertNotFound();
    }

    public function test_authorized_user_can_delete_post(){
        $user = User::factory()->create();

        $post = Post::create([
            'title' => 'Post to delete',
            'image' => 'posts/test.jpg',
            'user_id' => $user->id
        ]);

        $response = $this -> actingAs($user)->delete("/posts/{$post->id}");

        $response -> assertOk();

        $this->assertDatabaseMissing('posts',[
            'id' => $post->id
        ]);
    }

    public function test_guest_cannot_delete_post(){
        $user = User::factory()->create();

        $post = Post::create([
            'title' => 'Protected post',
            'image' => 'posts/protected_image.jpg',
            'user_id' => $user->id
        ]);

        $response = $this->delete("/posts/{$post->id}");

        $response->assertRedirect('/login');

        $this->assertDatabaseHas('posts',['id' => $post->id]);
    }

    public function test_user_cannot_delete_another_users_post(){
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();

        $post = Post::create([
            'title' => 'Owners post',
            'image' => 'posts/test.jpg',
            'user_id' => $owner->id
        ]);

        $response = $this -> actingAs($anotherUser)->delete("/posts/{$post->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('posts',[
            'id' => $post->id
        ]);
    }

    public function test_user_can_create_post_via_api(){
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/posts',[
            'title' => 'My API post',
            'image' => UploadedFile::fake()->image('post.jpg'),
        ]);

        $response -> assertCreated();

        $this->assertDatabaseHas('posts', [
            'title' => 'My API post',
            'user_id' => $user->id,
        ]);
    }

    public function test_api_post_requires_title(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'image' => UploadedFile::fake()->image('post.jpg'),
        ]);

        $response->assertUnprocessable() ->assertJsonValidationErrors('title');

        $this->assertDatabaseMissing('posts', [
            'user_id' => $user->id,
        ]);
    }

    public function test_api_post_requires_valid_image(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'title' => 'My API post',
            'image' => 'not-an-image',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_user_can_update_post_via_api(){
        Storage::fake('public');
        $user = User::factory()->create();
        $post = Post::create([
            'title' => 'Old title',
            'image' => 'post/test.jpg',
            'user_id' => $user->id
        ]);

        $response = $this-> actingAs($user) ->putJson("/api/posts/{$post->id}",[
            'title' => 'New title',
            'image' => UploadedFile::fake()->image('post.jpg'),
        ]);

        $response -> assertOk();

        $this->assertDatabaseHas('posts',[
            'id' => $post->id,
            'title' => 'New title'
        ]);
    }

    public function test_post_user_can_get_all_posts_via_api(){
        $user = User::factory()->create();
        Post::create([
            'title' => 'First post',
            'image' => 'posts/first.jpg',
            'user_id' => $user->id
        ]);

        Post::create([
            'title' => 'Second post',
            'image' => 'posts/second.jpg',
            'user_id' => $user->id
        ]);

        $response =  $this->actingAs($user)->getJson('/api/posts');

        $response->assertOk()
            ->assertJsonFragment([
                'title' => 'First post',
            ])
            ->assertJsonFragment([
                'title' => 'Second post',
            ]);
    }

    public function test_user_can_get_one_post_via_api(){
        $user = User::factory()->create();
        $post = Post::create([
            'title' => 'My post',
            'image' => 'posts/test.jpg',
            'user_id' => $user->id
        ]);

        $response = $this->actingAs($user)->getJson("/api/posts/{$post->id}");

        $response -> assertOk()
            ->assertJsonFragment([
                'id' => $post->id,
                'title' => 'My post'
            ]);
    }

    public function test_authorized_user_can_delete_post_via_api(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'title' => 'Post to delete',
            'image' => 'posts/test.jpg',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/posts/{$post->id}");

        $response->assertSuccessful();

        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_guest_cannot_delete_post_via_api(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'title' => 'Protected post',
            'image' => 'posts/test.jpg',
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson("/api/posts/{$post->id}");

        $response->assertUnauthorized();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
        ]);
    }

}
