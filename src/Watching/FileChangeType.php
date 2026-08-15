<?php

namespace HarrisonRatcliffe\QueueWatch\Watching;

enum FileChangeType: string
{
    case Created = 'created';
    case Modified = 'modified';
    case Deleted = 'deleted';
}
