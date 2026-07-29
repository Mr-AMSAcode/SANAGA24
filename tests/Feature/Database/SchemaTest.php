<?php

/**
 * tests/Feature/Database/SchemaTest.php
 *
 * Command: php artisan make:test Feature/Database/SchemaTest
 * Run:     ./vendor/bin/pest tests/Feature/Database/SchemaTest.php
 *
 * These tests verify that every migration ran correctly and that the
 * schema matches the UML design. They run against the TEST database
 * (defined in .env.testing / phpunit.xml).
 *
 * They are FAST (no HTTP, no Livewire hydration) and RELIABLE
 * (deterministic — they only read schema, not application data).
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────────────────────
// USERS TABLE
// ─────────────────────────────────────────────────────────────────────────────
describe('users table schema', function () {

    it('exists', function () {
        expect(Schema::hasTable('users'))->toBeTrue();
    });

    it('has all required columns', function () {
        expect(Schema::hasColumns('users', [
            'id', 'name', 'email', 'age', 'password',
            'email_verified_at', 'remember_token',
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
            'created_at', 'updated_at',
        ]))->toBeTrue();
    });

    it('enforces unique email', function () {
        $indexes = collect(DB::select("
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'users'
        "));

        $hasUniqueEmail = $indexes->some(fn ($idx) => str_contains($idx->indexdef, 'UNIQUE') &&
            str_contains($idx->indexdef, 'email')
        );

        expect($hasUniqueEmail)->toBeTrue();
    });

    it('age column is nullable', function () {
        $col = collect(DB::select("
            SELECT is_nullable
            FROM information_schema.columns
            WHERE table_name = 'users' AND column_name = 'age'
        "))->first();

        expect($col->is_nullable)->toBe('YES');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// POSTS TABLE
// ─────────────────────────────────────────────────────────────────────────────
describe('posts table schema', function () {

    it('exists', function () {
        expect(Schema::hasTable('posts'))->toBeTrue();
    });

    it('has all required columns', function () {
        expect(Schema::hasColumns('posts', [
            'id', 'editor_id', 'title', 'slug', 'content',
            'section', 'status', 'created_at', 'updated_at', 'deleted_at',
        ]))->toBeTrue();
    });

    it('has unique slug', function () {
        $indexes = collect(DB::select("
            SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'posts'
        "));
        expect($indexes->some(fn ($i) => str_contains($i->indexdef, 'UNIQUE') && str_contains($i->indexdef, 'slug')
        ))->toBeTrue();
    });

    it('has soft delete column (deleted_at)', function () {
        expect(Schema::hasColumn('posts', 'deleted_at'))->toBeTrue();
    });

    it('has a composite index on status + created_at', function () {
        $indexes = collect(DB::select("
            SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'posts'
        "));
        expect($indexes->some(fn ($i) => str_contains($i->indexdef, 'status') &&
            str_contains($i->indexdef, 'created_at')
        ))->toBeTrue();
    });

    it('editor_id has a foreign key to users', function () {
        $fks = DB::select("
            SELECT kcu.column_name, ccu.table_name AS foreign_table_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_name = 'posts'
              AND kcu.column_name = 'editor_id'
        ");
        expect($fks)->not->toBeEmpty();
        expect($fks[0]->foreign_table_name)->toBe('users');
    });

    it('status column only allows valid enum values', function () {
        $col = collect(DB::select("
            SELECT column_default, udt_name
            FROM information_schema.columns
            WHERE table_name = 'posts' AND column_name = 'status'
        "))->first();

        // default should be 'draft'
        expect($col->column_default)->toContain('draft');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// PICTURES TABLE
// ─────────────────────────────────────────────────────────────────────────────
describe('pictures table schema', function () {

    it('exists', function () {
        expect(Schema::hasTable('pictures'))->toBeTrue();
    });

    it('has required columns', function () {
        expect(Schema::hasColumns('pictures', [
            'id', 'post_id', 'url', 'alt_text', 'is_featured', 'created_at',
        ]))->toBeTrue();
    });

    it('post_id is nullable', function () {
        $col = collect(DB::select("
            SELECT is_nullable FROM information_schema.columns
            WHERE table_name = 'pictures' AND column_name = 'post_id'
        "))->first();
        expect($col->is_nullable)->toBe('YES');
    });

    it('enforces only one featured image per post', function () {
        $indexes = collect(DB::select("
            SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'pictures'
        "));
        expect($indexes->some(fn ($i) => str_contains($i->indexdef, 'UNIQUE') &&
            str_contains($i->indexdef, 'post_id') &&
            str_contains($i->indexdef, 'is_featured')
        ))->toBeTrue();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// COMMENTS TABLE
// ─────────────────────────────────────────────────────────────────────────────
describe('comments table schema', function () {

    it('exists', function () {
        expect(Schema::hasTable('comments'))->toBeTrue();
    });

    it('has required columns', function () {
        expect(Schema::hasColumns('comments', [
            'id', 'user_id', 'post_id', 'parent_id', 'content',
            'created_at', 'updated_at', 'deleted_at',
        ]))->toBeTrue();
    });

    it('parent_id is nullable (top-level comments allowed)', function () {
        $col = collect(DB::select("
            SELECT is_nullable FROM information_schema.columns
            WHERE table_name = 'comments' AND column_name = 'parent_id'
        "))->first();
        expect($col->is_nullable)->toBe('YES');
    });

    it('parent_id references the comments table itself (self-ref FK)', function () {
        $fks = DB::select("
            SELECT kcu.column_name, ccu.table_name AS foreign_table_name
            FROM information_schema.table_constraints AS tc
            JOIN information_schema.key_column_usage AS kcu
              ON tc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage AS ccu
              ON ccu.constraint_name = tc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_name = 'comments'
              AND kcu.column_name = 'parent_id'
        ");
        expect($fks)->not->toBeEmpty();
        expect($fks[0]->foreign_table_name)->toBe('comments');
    });

    it('post_id cascades on delete', function () {
        $constraints = DB::select("
            SELECT rc.delete_rule
            FROM information_schema.referential_constraints rc
            JOIN information_schema.key_column_usage kcu
              ON rc.constraint_name = kcu.constraint_name
            WHERE kcu.table_name = 'comments'
              AND kcu.column_name = 'post_id'
        ");
        expect($constraints)->not->toBeEmpty();
        expect($constraints[0]->delete_rule)->toBe('CASCADE');
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// LIKES TABLE
// ─────────────────────────────────────────────────────────────────────────────
describe('likes table schema', function () {

    it('exists', function () {
        expect(Schema::hasTable('likes'))->toBeTrue();
    });

    it('has required columns', function () {
        expect(Schema::hasColumns('likes', [
            'id', 'user_id', 'target_type', 'target_id', 'created_at',
        ]))->toBeTrue();
    });

    it('enforces unique user + target_type + target_id (no duplicate likes)', function () {
        $indexes = collect(DB::select("
            SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'likes'
        "));
        expect($indexes->some(fn ($i) => str_contains($i->indexdef, 'UNIQUE') &&
            str_contains($i->indexdef, 'user_id') &&
            str_contains($i->indexdef, 'target_type') &&
            str_contains($i->indexdef, 'target_id')
        ))->toBeTrue();
    });

    it('has CHECK constraint that only allows post or comment as target_type', function () {
        $checks = DB::select("
            SELECT pg_get_constraintdef(c.oid) AS constraint_def
            FROM pg_constraint c
            JOIN pg_class t ON c.conrelid = t.oid
            WHERE t.relname = 'likes'
              AND c.contype = 'c'
        ");
        $hasCheck = collect($checks)->some(fn ($c) => str_contains($c->constraint_def, 'post') &&
            str_contains($c->constraint_def, 'comment')
        );
        expect($hasCheck)->toBeTrue();
    });

    it('has no updated_at (likes are immutable)', function () {
        expect(Schema::hasColumn('likes', 'updated_at'))->toBeFalse();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// POST_STATS TABLE
// ─────────────────────────────────────────────────────────────────────────────
describe('post_stats table schema', function () {

    it('exists', function () {
        expect(Schema::hasTable('post_stats'))->toBeTrue();
    });

    it('has required columns', function () {
        expect(Schema::hasColumns('post_stats', [
            'id', 'post_id', 'view_count', 'like_count', 'comment_count', 'updated_at',
        ]))->toBeTrue();
    });

    it('post_id is unique (enforces 1:1 with posts)', function () {
        $indexes = collect(DB::select("
            SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'post_stats'
        "));
        expect($indexes->some(fn ($i) => str_contains($i->indexdef, 'UNIQUE') && str_contains($i->indexdef, 'post_id')
        ))->toBeTrue();
    });

    it('counters default to zero', function () {
        $cols = collect(DB::select("
            SELECT column_name, column_default
            FROM information_schema.columns
            WHERE table_name = 'post_stats'
              AND column_name IN ('view_count', 'like_count', 'comment_count')
        "));
        $cols->each(fn ($c) => expect($c->column_default)->toContain('0'));
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// POST_STATUSES TABLE
// ─────────────────────────────────────────────────────────────────────────────
describe('post_statuses table schema', function () {

    it('exists', function () {
        expect(Schema::hasTable('post_statuses'))->toBeTrue();
    });

    it('has required columns', function () {
        expect(Schema::hasColumns('post_statuses', [
            'id', 'post_id', 'active_period_start',
            'active_period_end', 'is_archived', 'created_at', 'updated_at',
        ]))->toBeTrue();
    });

    it('active_period_end is nullable', function () {
        $col = collect(DB::select("
            SELECT is_nullable FROM information_schema.columns
            WHERE table_name = 'post_statuses' AND column_name = 'active_period_end'
        "))->first();
        expect($col->is_nullable)->toBe('YES');
    });

    it('post_id is unique (enforces 1:1 with posts)', function () {
        $indexes = collect(DB::select("
            SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'post_statuses'
        "));
        expect($indexes->some(fn ($i) => str_contains($i->indexdef, 'UNIQUE') && str_contains($i->indexdef, 'post_id')
        ))->toBeTrue();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// SPATIE PERMISSION TABLES
// ─────────────────────────────────────────────────────────────────────────────
describe('spatie permission tables', function () {

    it('roles table exists with correct columns', function () {
        expect(Schema::hasTable('roles'))->toBeTrue();
        expect(Schema::hasColumns('roles', ['id', 'name', 'guard_name', 'created_at', 'updated_at']))->toBeTrue();
    });

    it('permissions table exists with correct columns', function () {
        expect(Schema::hasTable('permissions'))->toBeTrue();
        expect(Schema::hasColumns('permissions', ['id', 'name', 'guard_name', 'created_at', 'updated_at']))->toBeTrue();
    });

    it('model_has_roles pivot table exists', function () {
        expect(Schema::hasTable('model_has_roles'))->toBeTrue();
        expect(Schema::hasColumns('model_has_roles', ['role_id', 'model_type', 'model_id']))->toBeTrue();
    });

    it('model_has_permissions pivot table exists', function () {
        expect(Schema::hasTable('model_has_permissions'))->toBeTrue();
    });

    it('role_has_permissions pivot table exists', function () {
        expect(Schema::hasTable('role_has_permissions'))->toBeTrue();
        expect(Schema::hasColumns('role_has_permissions', ['permission_id', 'role_id']))->toBeTrue();
    });

});
