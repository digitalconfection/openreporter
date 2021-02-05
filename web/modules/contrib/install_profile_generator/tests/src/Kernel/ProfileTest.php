<?php

namespace Drupal\Tests\install_profile_generator\Kernel;

use Drupal\install_profile_generator\Profile;
use org\bovigo\vfs\vfsStream;

/**
 * Tests the Profile object.
 *
 * @group install_profile_generator
 * @coversDefaultClass \Drupal\install_profile_generator\Profile
 */
class ProfileTest extends TestBase {

  /**
   * @covers ::create
   */
  public function testCreate() {
    // Set up a virtual file to read.
    $vfs_root = vfsStream::setup('root');
    $profile_directory = vfsStream::newDirectory('profiles')->at($vfs_root);
    $file_uri = vfsStream::url('root');

    $profile = new Profile(
      'test',
      'Test profile',
      'A description',
      $file_uri,
      $this->container->get('file_system'),
      $this->container->get('config.factory')
    );
    $profile->create();
    $this->assertTrue($profile_directory->hasChild('test'));
    $this->assertTrue($profile_directory->getChild('test')->hasChild('test.info.yml'));
    // Test the info file can be parsed and its contents.
    $info = $this->container->get('info_parser')->parse($profile_directory->getChild('test')->getChild('test.info.yml')->url());
    $this->assertEquals('Test profile', $info['name']);
    $this->assertEquals('profile', $info['type']);
    $this->assertEquals('A description', $info['description']);
    $this->assertEquals('8.x', $info['core']);
    // Ensure the profile's config/sync directory exists.
    $this->assertTrue($profile_directory->getChild('test')->getChild('config')->hasChild('sync'));
  }

  /**
   * @covers ::create
   */
  public function testCreateException() {
    $this->expectExceptionMessage('Could not create vfs://root/profiles/test directory');
    // Set up a virtual file to read with a read only profiles directory.
    $vfs_root = vfsStream::setup('root');
    vfsStream::newDirectory('profiles', 0444)->at($vfs_root);
    $file_uri = vfsStream::url('root');
    $profile = new Profile(
      'test',
      'Test profile',
      'A description',
      $file_uri,
      $this->container->get('file_system'),
      $this->container->get('config.factory')
    );
    $profile->create();
  }

}
