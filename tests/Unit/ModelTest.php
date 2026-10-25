<?php

namespace Tests\Unit;

use Core\Database\DB;
use Core\Database\MassAssignmentException;
use Core\Database\ModelNotFoundException;
use Core\Support\Collection;
use Tests\Fixtures\BlogModel;
use Tests\Fixtures\Kategori;
use Tests\Fixtures\Post;
use Tests\TestCase;

class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, status TEXT, views INTEGER, secret TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE blogs (id INTEGER PRIMARY KEY AUTOINCREMENT, baslik TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE kategoriler (kid INTEGER PRIMARY KEY AUTOINCREMENT, ad TEXT)');
    }

    public function testTableNameIsDerivedFromClassName(): void
    {
        $this->assertSame('posts', (new Post())->getTable());
        $this->assertSame('blogs', (new BlogModel())->getTable());       // "Model" son eki atılır
        $this->assertSame('kategoriler', (new Kategori())->getTable());  // açık $table
    }

    public function testStaticChainFromThePlan(): void
    {
        // Planın hedef örneği: BlogModel::where('durum', 'aktif')->orderBy('id', 'DESC')->get();
        BlogModel::create(['baslik' => 'ilk']);
        BlogModel::create(['baslik' => 'ikinci']);

        $list = BlogModel::where('baslik', '!=', 'yok')->orderBy('id', 'DESC')->get();

        $this->assertInstanceOf(Collection::class, $list);
        $this->assertInstanceOf(BlogModel::class, $list->first());
        $this->assertSame('ikinci', $list->first()->baslik);
        $this->assertCount(2, $list);
    }

    public function testCreateFindAndAttributes(): void
    {
        $post = Post::create(['title' => 'merhaba', 'status' => 'AKTIF', 'views' => 3]);

        $this->assertTrue($post->exists);
        $this->assertSame(1, $post->id);

        $found = Post::find(1);
        $this->assertInstanceOf(Post::class, $found);
        $this->assertSame('MERHABA', $found->title);   // accessor
        $this->assertSame('aktif', $found->status);    // mutator
        $this->assertNull(Post::find(99));
    }

    public function testFindReturnsModelNotArray(): void
    {
        Post::create(['title' => 'x']);

        $this->assertInstanceOf(Post::class, Post::find(1));
        $this->assertInstanceOf(Post::class, Post::where('id', 1)->first());
    }

    public function testFindOrFailThrows(): void
    {
        $this->expectException(ModelNotFoundException::class);
        Post::findOrFail(404);
    }

    public function testAllReturnsCollectionOfModels(): void
    {
        Post::create(['title' => 'a']);
        Post::create(['title' => 'b']);

        $all = Post::all();

        $this->assertCount(2, $all);
        $this->assertInstanceOf(Post::class, $all[1]);
    }

    public function testMassAssignmentIsBlockedForNonFillable(): void
    {
        $this->expectException(MassAssignmentException::class);
        Post::create(['title' => 'x', 'secret' => 'hack']);
    }

    public function testDirectPropertyAssignmentIsAllowed(): void
    {
        $post = new Post();
        $post->secret = 'iç';
        $post->title = 't';
        $post->save();

        $this->assertSame('iç', Post::find(1)->secret);
    }

    public function testSaveUpdatesOnlyDirtyAttributes(): void
    {
        $post = Post::create(['title' => 'eski', 'views' => 1]);
        $post->views = 2;

        $this->assertTrue($post->isDirty('views'));
        $this->assertFalse($post->isDirty('title'));

        $post->save();

        $this->assertFalse($post->isDirty());
        $this->assertEquals(2, Post::find(1)->views);
    }

    public function testTimestampsAreFilled(): void
    {
        $post = Post::create(['title' => 'x']);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $post->created_at);
        $this->assertSame($post->created_at, $post->updated_at);

        $post->title = 'y';
        $post->updated_at = '2000-01-01 00:00:00';
        $post->save();
        $this->assertSame('2000-01-01 00:00:00', Post::find(1)->updated_at);
    }

    public function testTimestampsCanBeDisabled(): void
    {
        $k = Kategori::create(['ad' => 'haber']);

        $this->assertSame(1, $k->kid);
        $this->assertArrayNotHasKey('created_at', $k->getAttributes());
        $this->assertSame('haber', Kategori::find(1)->ad);
    }

    public function testDelete(): void
    {
        $post = Post::create(['title' => 'x']);

        $this->assertTrue($post->delete());
        $this->assertFalse($post->exists);
        $this->assertNull(Post::find(1));
    }

    public function testBuilderUpdateAndDeleteViaModelChain(): void
    {
        Post::create(['title' => 'a', 'status' => 'aktif']);
        Post::create(['title' => 'b', 'status' => 'aktif']);
        Post::create(['title' => 'c', 'status' => 'taslak']);

        $this->assertSame(2, Post::where('status', 'aktif')->update(['views' => 9]));
        $this->assertSame(1, Post::where('status', 'taslak')->delete());
        $this->assertSame(2, Post::count());
    }

    public function testHiddenAttributesAreExcludedFromArrayAndJson(): void
    {
        $post = new Post();
        $post->forceFill(['title' => 'x', 'secret' => 's']);

        $this->assertArrayNotHasKey('secret', $post->toArray());
        $this->assertStringNotContainsString('secret', $post->toJson());
    }

    public function testCollectionJsonSerialization(): void
    {
        Post::create(['title' => 'a']);

        $json = json_encode(Post::all());

        $this->assertStringContainsString('"title":"a"', str_replace('A', 'a', $json));
        $this->assertStringNotContainsString('secret', $json);
    }

    public function testRefresh(): void
    {
        $post = Post::create(['title' => 'a', 'views' => 1]);
        DB::table('posts')->where('id', 1)->update(['views' => 77]);

        $post->refresh();

        $this->assertEquals(77, $post->views);
    }

    public function testArrayAccess(): void
    {
        $post = Post::create(['title' => 'a']);

        $this->assertSame('A', $post['title']);
        $this->assertTrue(isset($post['title']));
    }

    public function testUnknownBuilderMethodThrowsClearError(): void
    {
        $this->expectException(\BadMethodCallException::class);
        Post::olmayanMetot();
    }

    public function testNewModelInstancesAreIndependent(): void
    {
        $a = new Post();
        $b = new Post();
        $a->title = 'x';

        $this->assertNull($b->title);
    }

    public function testStaticQueriesDoNotShareState(): void
    {
        Post::create(['title' => 'a', 'status' => 'aktif']);
        Post::create(['title' => 'b', 'status' => 'taslak']);

        $q1 = Post::where('status', 'aktif');
        $q2 = Post::where('status', 'taslak');

        $this->assertSame('A', $q1->first()->title);
        $this->assertSame('B', $q2->first()->title);
    }

    public function testPaginate(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Post::create(['title' => "t{$i}"]);
        }

        $page = Post::orderBy('id')->paginate(10, 3);

        $this->assertSame(25, $page->total());
        $this->assertSame(3, $page->lastPage());
        $this->assertCount(5, $page);
        $this->assertFalse($page->hasMorePages());
        $this->assertInstanceOf(Post::class, $page->items()->first());
    }
}
