<?php
require __DIR__.'/vendor/autoload.php';
$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$userRepo = $em->getRepository(App\Entity\User::class);
$testRepo = $em->getRepository(App\Entity\Test::class);

$user = $userRepo->find(10); // ID 10 is the psychologist with some tests
if ($user) {
    echo "Found user 10: " . $user->getUsername() . "\n";
    $tests = $testRepo->findBy(['user' => $user]);
    echo "Tests found for user 10: " . count($tests) . "\n";
} else {
    echo "User 10 not found.\n";
}

$user16 = $userRepo->find(16);
if ($user16) {
    echo "Found user 16: " . $user16->getUsername() . "\n";
    $tests = $testRepo->findBy(['user' => $user16]);
    echo "Tests found for user 16: " . count($tests) . "\n";
}
