<?php

namespace ArchitectureDoctor\Policies;

/**
 * Şu an yalnızca iki bağlam var: bu repoda henüz bir CI pipeline'ı (.github/workflows,
 * GitLab CI, Jenkinsfile) bulunmuyor. PR/main/release gibi ek bağlamlar, gerçek bir
 * pipeline kurulduğunda eklenir — Policy bunu değiştirmeden destekleyecek şekilde tasarlandı.
 */
enum ExecutionContext: string
{
    case Local = 'local';
    case Ci = 'ci';
}
