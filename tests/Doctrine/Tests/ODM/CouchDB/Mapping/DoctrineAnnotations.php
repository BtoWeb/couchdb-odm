<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\CouchDB\Mapping;

use Doctrine\Common\Annotations\Annotation;

final class Document extends Annotation
{
    public $db;
    public $collection;
    public $repositoryClass;
    public $indexes = array();
}
final class EmbeddedDocument extends Annotation {}

class Field extends Annotation
{
    public $name;
    public $type = 'string';
}
final class Id extends Field
{
    public $id = true;
    public $type = 'id';
    public $custom = false;
}
final class Hash extends Field
{
    public $type = 'hash';
}
final class Boolean extends Field
{
    public $type = 'boolean';
}
final class Int extends Field
{
    public $type = 'int';
}
final class Float extends Field
{
    public $type = 'float';
}
final class String extends Field
{
    public $type = 'string';
}
final class Date extends Field
{
    public $type = 'date';
}
final class Key extends Field
{
    public $type = 'key';
}
final class Timestamp extends Field
{
    public $type = 'timestamp';
}
final class Bin extends Field
{
    public $type = 'bin';
}
final class BinFunc extends Field
{
    public $type = 'bin_func';
}
final class BinUUID extends Field
{
    public $type = 'bin_uuid';
}
final class BinMD5 extends Field
{
    public $type = 'bin_md5';
}
final class BinCustom extends Field
{
    public $type = 'bin_custom';
}
final class File extends Field
{
    public $type = 'file';
    public $file = true;
}
final class Increment extends Field
{
    public $type = 'increment';
}
final class EmbedOne extends Field
{
    public $type = 'one';
    public $embedded = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
}
final class EmbedMany extends Field
{
    public $type = 'many';
    public $embedded = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
    public $strategy = 'pushPull'; // pushPull, set
}
final class ReferenceOne extends Field
{
    public $type = 'one';
    public $reference = true;
    public $targetDocument;
    public $discriminatorField;
    public $discriminatorMap;
    public $cascade;
}
final class ReferenceMany extends Field
{
    public $type = 'many';
    public $reference = true;
    public $targetDocument;
}
class NotSaved extends Field {}
final class AlsoLoad extends Annotation
{
    public $name;
}