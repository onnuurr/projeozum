<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\TrainingIngest\Database\Migrations\Concerns\SkipsNonPostgres;

/**
 * Spec ADIM 5'in 5 sorusuna karşılık gelen salt-okuma view'ler. creative:review-report'un
 * YERİNE geçmez, onu besler. Yalnızca GÜNCEL kararları (superseded_by IS NULL) sayar —
 * düzeltilmiş/eski kararlar oran hesaplarını çarpıtmasın diye.
 */
return new class extends Migration
{
    use SkipsNonPostgres;

    protected $connection = 'training';

    private const VIEWS = [
        'v_rejection_rate_by_prompt_version' => <<<'SQL'
            CREATE VIEW v_rejection_rate_by_prompt_version AS
            SELECT
                pv.id AS prompt_version_id,
                pv.version_label,
                pv.is_active,
                COUNT(r.id) AS total_reviews,
                COUNT(r.id) FILTER (WHERE r.verdict = 'rejected') AS rejected_count,
                ROUND(100.0 * COUNT(r.id) FILTER (WHERE r.verdict = 'rejected') / NULLIF(COUNT(r.id), 0), 2) AS rejection_rate_pct
            FROM prompt_versions pv
            JOIN generations g ON g.prompt_version_id = pv.id
            JOIN generation_outputs go ON go.generation_id = g.id
            JOIN reviews r ON r.generation_output_id = go.id AND r.superseded_by IS NULL
            GROUP BY pv.id, pv.version_label, pv.is_active
            SQL,

        'v_rejection_reasons' => <<<'SQL'
            CREATE VIEW v_rejection_reasons AS
            SELECT
                r.id AS review_id,
                r.reason_code,
                rr.label AS reason_label,
                r.reviewed_at,
                g.product_id
            FROM reviews r
            JOIN rejection_reasons rr ON rr.code = r.reason_code
            JOIN generation_outputs go ON go.id = r.generation_output_id
            JOIN generations g ON g.id = go.generation_id
            WHERE r.verdict = 'rejected' AND r.superseded_by IS NULL
            SQL,

        'v_rejection_rate_by_category' => <<<'SQL'
            CREATE VIEW v_rejection_rate_by_category AS
            SELECT
                p.category,
                COUNT(r.id) AS total_reviews,
                COUNT(r.id) FILTER (WHERE r.verdict = 'rejected') AS rejected_count,
                ROUND(100.0 * COUNT(r.id) FILTER (WHERE r.verdict = 'rejected') / NULLIF(COUNT(r.id), 0), 2) AS rejection_rate_pct
            FROM products p
            JOIN generations g ON g.product_id = p.id
            JOIN generation_outputs go ON go.generation_id = g.id
            JOIN reviews r ON r.generation_output_id = go.id AND r.superseded_by IS NULL
            GROUP BY p.category
            SQL,

        'v_rejection_rate_by_mannequin_pose' => <<<'SQL'
            CREATE VIEW v_rejection_rate_by_mannequin_pose AS
            SELECT
                m.external_id AS mannequin_external_id,
                m.label AS mannequin_label,
                ps.external_id AS pose_external_id,
                ps.label AS pose_label,
                COUNT(r.id) AS total_reviews,
                COUNT(r.id) FILTER (WHERE r.verdict = 'rejected') AS rejected_count,
                ROUND(100.0 * COUNT(r.id) FILTER (WHERE r.verdict = 'rejected') / NULLIF(COUNT(r.id), 0), 2) AS rejection_rate_pct
            FROM generations g
            JOIN mannequins m ON m.id = g.mannequin_id
            JOIN poses ps ON ps.id = g.pose_id
            JOIN generation_outputs go ON go.generation_id = g.id
            JOIN reviews r ON r.generation_output_id = go.id AND r.superseded_by IS NULL
            GROUP BY m.external_id, m.label, ps.external_id, ps.label
            SQL,

        'v_approval_trend' => <<<'SQL'
            CREATE VIEW v_approval_trend AS
            SELECT
                date_trunc('day', r.reviewed_at) AS day,
                COUNT(r.id) AS total_reviews,
                COUNT(r.id) FILTER (WHERE r.verdict = 'approved') AS approved_count,
                ROUND(100.0 * COUNT(r.id) FILTER (WHERE r.verdict = 'approved') / NULLIF(COUNT(r.id), 0), 2) AS approval_rate_pct
            FROM reviews r
            WHERE r.superseded_by IS NULL
            GROUP BY date_trunc('day', r.reviewed_at)
            SQL,
    ];

    public function up(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        foreach (self::VIEWS as $sql) {
            DB::connection($this->connection)->statement($sql);
        }
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        foreach (array_keys(self::VIEWS) as $name) {
            DB::connection($this->connection)->statement("DROP VIEW IF EXISTS {$name}");
        }
    }
};
